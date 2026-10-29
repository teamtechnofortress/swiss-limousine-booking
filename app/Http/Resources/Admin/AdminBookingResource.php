<?php

namespace App\Http\Resources\Admin;

use App\Enums\BookingStatus;
use App\Http\Resources\BookingLegResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AdminBookingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $isNew = $this->admin_seen_at === null
            && in_array($this->status, [BookingStatus::Confirmed, BookingStatus::NeedsReview], true);

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'source' => $this->source,
            'is_new' => $isNew,
            'admin_seen_at' => $this->admin_seen_at?->toIso8601String(),
            'customer' => [
                'name' => $this->customer_name,
                'email' => $this->customer_email,
                'phone' => $this->customer_phone,
                'flight_number' => $this->flight_number,
            ],
            'vehicle' => [
                'id' => $this->vehicle?->id,
                'name' => $this->vehicle?->name,
            ],
            'fleet_unit' => $this->fleetUnit === null ? null : [
                'id' => $this->fleetUnit->id,
                'plate' => $this->fleetUnit->plate,
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
                'breakdown' => $this->price_breakdown,
            ],
            'payment' => $this->payment === null ? null : [
                'method' => $this->payment->method->value,
                'status' => $this->payment->status->value,
                'gross' => $this->payment->gross_amount,
                'vat' => $this->payment->vat_amount,
                'paid_at' => $this->payment->paid_at?->toIso8601String(),
                'refunds' => $this->payment->relationLoaded('refunds')
                    ? $this->payment->refunds->map(fn ($refund) => [
                        'id' => $refund->id,
                        'gross' => $refund->gross_amount,
                        'vat' => $refund->vat_amount,
                        'status' => $refund->status,
                        'reason' => $refund->reason,
                        'created_at' => $refund->created_at?->toIso8601String(),
                    ])->values()
                    : null,
            ],
            'legs' => BookingLegResource::collection($this->whenLoaded('legs')),
            'adjustments' => $this->relationLoaded('adjustments')
                ? $this->adjustments->map(fn ($adjustment) => [
                    'id' => $adjustment->id,
                    'type' => $adjustment->type,
                    'minutes' => $adjustment->minutes,
                    'gross' => $adjustment->gross_amount,
                    'vat' => $adjustment->vat_amount,
                    'status' => $adjustment->status,
                    'reason' => $adjustment->reason,
                ])->values()
                : null,
            'cancellation' => [
                'requested_at' => $this->cancellation_requested_at?->toIso8601String(),
                'reason' => $this->cancellation_reason,
                'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            ],
            'hold_expires_at' => $this->hold_expires_at?->toIso8601String(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
