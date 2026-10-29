<?php

namespace App\Http\Resources\Public;

use App\Http\Resources\BookingLegResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Customer view. No internal ids, tokens or other customers' data. */
final class PublicBookingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'payment_method' => $this->payment_method->value,
            'payment_status' => $this->payment?->status?->value,
            'vehicle' => [
                'id' => $this->vehicle?->id,
                'name' => $this->vehicle?->name,
            ],
            'passengers' => $this->passengers,
            'luggage' => $this->luggage,
            'price' => [
                'currency' => $this->currency,
                'net' => $this->taxable_amount,
                'vat_rate_bp' => $this->vat_rate_bp,
                'vat' => $this->vat_amount,
                'total' => $this->total_amount,
                'vat_included' => $this->vat_included,
                'vat_registration_number' => $this->vat_registration_number,
            ],
            'legs' => BookingLegResource::collection($this->legs),
            'hold_expires_at' => $this->hold_expires_at?->toIso8601String(),
            'cancellation_requested_at' => $this->cancellation_requested_at?->toIso8601String(),
        ];
    }
}
