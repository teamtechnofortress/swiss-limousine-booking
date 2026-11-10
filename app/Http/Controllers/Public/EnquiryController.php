<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreEnquiryRequest;
use App\Models\Booking;
use App\Models\Enquiry;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class EnquiryController extends Controller
{
    public function store(StoreEnquiryRequest $request): JsonResponse
    {
        return $this->save($request);
    }

    /** Kept so existing trip pages can submit while the frontend moves to /public/enquiries. */
    public function storeTrip(StoreEnquiryRequest $request): JsonResponse
    {
        return $this->save($request);
    }

    private function save(StoreEnquiryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $trip = $this->trip($data['trip_slug'] ?? null);
        $vehicle = $this->vehicle($data['vehicle_slug'] ?? null);
        $booking = $this->booking($data['booking_reference'] ?? null);

        $subjectParts = collect([$trip?->title, $vehicle?->name])
            ->filter()
            ->values();
        if ($booking !== null) {
            $subjectParts->push('Booking '.$booking->reference);
        }

        $enquiry = Enquiry::query()->create([
            'type' => $data['type'],
            'trip_id' => $trip?->id,
            'vehicle_id' => $vehicle?->id,
            'booking_id' => $booking?->id,
            // The server builds this snapshot from its own records. A browser-supplied
            // title is intentionally not accepted as the source of truth.
            'subject_title' => $subjectParts->isEmpty() ? null : $subjectParts->implode(' — '),
            'source_url' => $data['source_url'] ?? null,
            'customer_name' => $data['name'],
            'customer_email' => $data['email'],
            'customer_phone' => $data['phone'] ?? null,
            'preferred_contact_method' => $data['preferred_contact_method'] ?? null,
            'travel_date' => $data['travel_date'] ?? null,
            'return_date' => $data['return_date'] ?? null,
            'passengers' => $data['passengers'] ?? null,
            'luggage' => $data['luggage'] ?? null,
            'pickup_location' => $data['pickup_location'] ?? null,
            'dropoff_location' => $data['dropoff_location'] ?? null,
            'message' => $data['message'],
        ]);

        return response()->json(['data' => [
            'reference' => $enquiry->reference,
            'message' => 'Your enquiry has been received.',
        ]], 201);
    }

    private function trip(?string $slug): ?Trip
    {
        if ($slug === null) {
            return null;
        }

        $trip = Trip::query()->published()->where('slug', $slug)->first();
        if ($trip === null) {
            throw ValidationException::withMessages(['trip_slug' => 'The selected trip is not available.']);
        }

        return $trip;
    }

    private function vehicle(?string $slug): ?Vehicle
    {
        if ($slug === null) {
            return null;
        }

        $vehicle = Vehicle::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->whereHas('category', fn ($categories) => $categories->where('is_active', true))
            ->first();
        if ($vehicle === null) {
            throw ValidationException::withMessages(['vehicle_slug' => 'The selected vehicle is not available.']);
        }

        return $vehicle;
    }

    private function booking(?string $reference): ?Booking
    {
        if ($reference === null) {
            return null;
        }

        $booking = Booking::query()->where('reference', $reference)->first();
        if ($booking === null) {
            throw ValidationException::withMessages(['booking_reference' => 'The booking reference could not be found.']);
        }

        return $booking;
    }
}
