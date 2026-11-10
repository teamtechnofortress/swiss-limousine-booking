<?php

namespace App\Http\Requests\Public;

use App\Models\Enquiry;
use App\Rules\TurnstileToken;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $context = [];

        if ($this->route('slug') !== null) {
            $context = ['type' => 'trip', 'trip_slug' => $this->route('slug')];
        }

        if (! $this->filled('passengers') && $this->filled('travellers')) {
            $context['passengers'] = $this->input('travellers');
        }

        $this->merge($context);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $turnstile = app(SettingsService::class)->bool('turnstile_enabled') ? 'required' : 'nullable';

        return [
            'type' => ['required', 'string', Rule::in(Enquiry::TYPES)],
            'trip_slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'vehicle_slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'booking_reference' => ['nullable', 'string', 'max:32'],
            'source_url' => ['nullable', 'string', 'max:2048'],

            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'preferred_contact_method' => ['nullable', Rule::in(['email', 'phone', 'whatsapp'])],

            'travel_date' => ['nullable', 'date'],
            'return_date' => ['nullable', 'date', 'after_or_equal:travel_date'],
            'passengers' => ['nullable', 'integer', 'min:1', 'max:99'],
            'travellers' => ['nullable', 'integer', 'min:1', 'max:99'],
            'luggage' => ['nullable', 'integer', 'min:0', 'max:99'],
            'pickup_location' => ['nullable', 'string', 'max:255'],
            'dropoff_location' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:3000'],
            'turnstile_token' => [$turnstile, 'string', 'max:2048', new TurnstileToken],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $type = $this->input('type');

            if ($type === 'trip' && ! $this->filled('trip_slug')) {
                $validator->errors()->add('trip_slug', 'A trip is required for a trip enquiry.');
            }

            if ($type === 'vehicle' && ! $this->filled('vehicle_slug')) {
                $validator->errors()->add('vehicle_slug', 'A vehicle is required for a vehicle enquiry.');
            }

            if ($type === 'booking' && ! $this->filled('booking_reference')) {
                $validator->errors()->add('booking_reference', 'A booking reference is required for a booking enquiry.');
            }
        });
    }
}
