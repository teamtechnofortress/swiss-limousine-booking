<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class BookingLegResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'direction' => $this->direction,
            'pickup' => $this->pickup['address'] ?? null,
            'dropoff' => $this->dropoff['address'] ?? null,
            'starts_at' => $this->starts_at->toIso8601String(),
            'amount' => $this->amount,
        ];
    }
}
