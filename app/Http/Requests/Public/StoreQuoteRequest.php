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
            'pickup_at' => ['required', 'date'],
            'return_at' => ['nullable', 'date', 'after:pickup_at'],
            'passengers' => ['required', 'integer', 'between:1,20'],
            'luggage' => ['required', 'integer', 'between:0,30'],
            'turnstile_token' => [$turnstile, 'string', 'max:2048', new TurnstileToken()],
        ];
    }

    public function toQuoteRequest(): QuoteRequestData
    {
        return new QuoteRequestData(
            pickupPlaceId: (string) $this->input('pickup_place_id'),
            dropoffPlaceId: (string) $this->input('dropoff_place_id'),
            pickupAt: CarbonImmutable::parse((string) $this->input('pickup_at'))->utc(),
            returnAt: $this->filled('return_at')
                ? CarbonImmutable::parse((string) $this->input('return_at'))->utc()
                : null,
            passengers: (int) $this->input('passengers'),
            luggage: (int) $this->input('luggage'),
        );
    }
}
