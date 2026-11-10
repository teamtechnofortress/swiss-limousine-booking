<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AdminEnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(Enquiry::TYPES)],
            'status' => ['nullable', Rule::in(Enquiry::STATUSES)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'unassigned' => ['nullable', 'boolean'],
            'unread' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $enquiries = Enquiry::query()
            ->with(['trip', 'vehicle', 'booking', 'assignedTo'])
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['assigned_to'] ?? null, fn ($query, int $adminId) => $query->where('assigned_to', $adminId))
            ->when($request->boolean('unassigned'), fn ($query) => $query->whereNull('assigned_to'))
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $term = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($search) => $search
                    ->where('reference', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_email', 'like', $term)
                    ->orWhere('subject_title', 'like', $term));
            })
            ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'unread' => Enquiry::query()->whereNull('read_at')->count(),
            'active' => Enquiry::query()->whereIn('status', ['new', 'contacted', 'quoted'])->count(),
            'today' => Enquiry::query()->whereDate('created_at', today())->count(),
        ];
        $admins = User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('admin.enquiries.index', compact('enquiries', 'filters', 'stats', 'admins'));
    }

    public function show(Enquiry $enquiry): View
    {
        if ($enquiry->read_at === null) {
            $enquiry->update(['read_at' => now()]);
        }

        $enquiry->load(['trip', 'vehicle', 'booking', 'assignedTo']);
        $admins = User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('admin.enquiries.show', compact('enquiry', 'admins'));
    }

    public function update(Enquiry $enquiry, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Enquiry::STATUSES)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        if ($data['status'] !== 'new' && $enquiry->handled_at === null) {
            $data['handled_at'] = now();
        }
        if ($enquiry->read_at === null) {
            $data['read_at'] = now();
        }

        $enquiry->update($data);

        return back()->with('status', 'Enquiry updated.');
    }
}
