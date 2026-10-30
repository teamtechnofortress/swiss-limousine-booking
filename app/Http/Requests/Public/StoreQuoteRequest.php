<?php

namespace App\Http\Requests\Public;

use App\Data\QuoteRequestData;
use App\Rules\TurnstileToken;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class StoreQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // When bot checks are on, the token is required. "nullable" here would let requests skip the check.
        $turnstile = app(SettingsService::class)->bool('turnstile_enabled') ? 'required' : 'nullable';

        return [
            'pickup_place_id' => ['required', 'string', 'max:255'],
            'dropoff_place_id' => ['required', 'string', 'max:255', 'different:pickup_place_id'],
            'pickup_address' => ['required', 'string', 'min:3', 'max:255'],
            'dropoff_address' => ['required', 'string', 'min:3', 'max:255'],
            'pickup_session_token' => ['nullable', 'string', 'max:255'],
            'dropoff_session_token' => ['nullable', 'string', 'max:255'],
            'pickup_at' => ['required', 'date'],
            'return_at' => ['nullable', 'date', 'after:pickup_at'],
            'passengers' => ['required', 'integer', 'between:1,20'],
            'luggage' => ['required', 'integer', 'between:0,30'],
            'vehicle_slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'turnstile_token' => [$turnstile, 'string', 'max:2048', new TurnstileToken],
        ];
    }

    public function toQuoteRequest(): QuoteRequestData
    {
        return new QuoteRequestData(
            pickupPlaceId: (string) $this->input('pickup_place_id'),
            dropoffPlaceId: (string) $this->input('dropoff_place_id'),
            pickupAddress: trim((string) $this->input('pickup_address')),
            dropoffAddress: trim((string) $this->input('dropoff_address')),
            pickupSessionToken: $this->filled('pickup_session_token') ? (string) $this->input('pickup_session_token') : null,
            dropoffSessionToken: $this->filled('dropoff_session_token') ? (string) $this->input('dropoff_session_token') : null,
            pickupAt: CarbonImmutable::parse((string) $this->input('pickup_at'))->utc(),
            returnAt: $this->filled('return_at')
                ? CarbonImmutable::parse((string) $this->input('return_at'))->utc()
                : null,
            passengers: (int) $this->input('passengers'),
            luggage: (int) $this->input('luggage'),
            vehicleSlug: $this->filled('vehicle_slug') ? (string) $this->input('vehicle_slug') : null,
        );
    }
}
