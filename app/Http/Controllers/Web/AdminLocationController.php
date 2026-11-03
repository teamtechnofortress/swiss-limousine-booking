<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\Geo\GoogleMapsClient;
use App\Services\Locations\LocationManager;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

final class AdminLocationController extends Controller
{
    public function __construct(
        private readonly LocationManager $locations,
        private readonly GoogleMapsClient $maps,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'state' => ['nullable', 'in:active,inactive,featured,allowed_pickup']]);
        $locations = Location::query()
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $term = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('formatted_address', 'like', $term)->orWhere('country_code', 'like', $term));
            })
            ->when(($filters['state'] ?? '') === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['state'] ?? '') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when(($filters['state'] ?? '') === 'featured', fn ($q) => $q->where('is_featured', true))
            ->when(($filters['state'] ?? '') === 'allowed_pickup', fn ($q) => $q->where('allowed_pickup', true))
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.locations.index', compact('locations', 'filters'));
    }

    public function create(): View { return view('admin.locations.create'); }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:150']]);
        return response()->json(['data' => $this->maps->locationPredictions($data['q'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request, true);
        try {
            $location = $this->locations->createFromGoogle($data['google_place_id'], $data, $request->user()?->id);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['google_place_id' => $e->getMessage()]);
        }
        return redirect()->route('admin.locations.edit', $location)->with('status', 'Location added from Google and ready to manage.');
    }

    /** Lightweight JSON variant used by catalogue pickers inside other admin modules. */
    public function quickStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'google_place_id' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:150'],
        ]);

        try {
            $location = $this->locations->createFromGoogle($data['google_place_id'], [
                ...$data, 'is_active' => true, 'is_featured' => false, 'allowed_pickup' => false,
            ], $request->user()?->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => [
            'id' => $location->id,
            'name' => $location->name,
            'address' => $location->formatted_address,
            'place_id' => $location->google_place_id,
        ]], 201);
    }

    public function edit(Location $location): View { return view('admin.locations.edit', compact('location')); }

    public function update(Location $location, Request $request): RedirectResponse
    {
        try {
            $this->locations->update($location, $this->validatePayload($request), $request->user()?->id);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['location' => $e->getMessage()]);
        }
        return back()->with('status', 'Location changes saved.');
    }

    public function refresh(Location $location, Request $request): RedirectResponse
    {
        try { $this->locations->refresh($location, $request->user()?->id); }
        catch (RuntimeException $e) { return back()->withErrors(['location' => 'Google refresh failed: '.$e->getMessage()]); }
        return back()->with('status', 'Google place details refreshed.');
    }

    public function hero(Location $location, Request $request): RedirectResponse
    {
        $request->validate(['hero' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']]);
        $location->addMediaFromRequest('hero')->toMediaCollection('hero');
        return back()->with('status', 'Location hero image saved.');
    }

    public function destroy(Location $location, Request $request): RedirectResponse
    {
        try { $this->locations->delete($location, $request->user()?->id); }
        catch (RuntimeException $e) { return back()->withErrors(['location' => $e->getMessage()]); }
        return redirect()->route('admin.locations.index')->with('status', 'Location removed. Existing bookings remain unchanged.');
    }

    private function validatePayload(Request $request, bool $creating = false): array
    {
        return $request->validate([
            'google_place_id' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:150'], 'slug' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'], 'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'], 'is_featured' => ['nullable', 'boolean'], 'allowed_pickup' => ['nullable', 'boolean'],
        ]);
    }
}
