<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdminEnquiryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => $this->type,
            'status' => $this->status,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'subject_title' => $this->subject_title,
            'source_url' => $this->source_url,
            'customer' => [
                'name' => $this->customer_name,
                'email' => $this->customer_email,
                'phone' => $this->customer_phone,
                'preferred_contact_method' => $this->preferred_contact_method,
            ],
            'trip' => $this->trip === null ? null : [
                'id' => $this->trip->id,
                'slug' => $this->trip->slug,
                'title' => $this->trip->title,
            ],
            'vehicle' => $this->vehicle === null ? null : [
                'id' => $this->vehicle->id,
                'slug' => $this->vehicle->slug,
                'name' => $this->vehicle->name,
            ],
            'booking' => $this->booking === null ? null : [
                'id' => $this->booking->id,
                'reference' => $this->booking->reference,
            ],
            'journey' => [
                'travel_date' => $this->travel_date?->toDateString(),
                'return_date' => $this->return_date?->toDateString(),
                'passengers' => $this->passengers,
                'luggage' => $this->luggage,
                'pickup_location' => $this->pickup_location,
                'dropoff_location' => $this->dropoff_location,
            ],
            'message' => $this->message,
            'assignment' => [
                'admin' => $this->assignedTo === null ? null : [
                    'id' => $this->assignedTo->id,
                    'name' => $this->assignedTo->name,
                    'email' => $this->assignedTo->email,
                ],
                'handled_at' => $this->handled_at?->toIso8601String(),
            ],
            'internal_notes' => $this->internal_notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
