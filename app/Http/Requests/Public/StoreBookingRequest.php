<?php

namespace App\Http\Requests\Public;

use App\Data\CreateBookingData;
use App\Enums\PaymentMethod;
use App\Rules\TurnstileToken;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $turnstile = app(SettingsService::class)->bool('turnstile_enabled') ? 'required' : 'nullable';

        return [
            'quote_token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'payment_method' => ['required', Rule::in(['online', 'cash_on_arrival'])],
            'customer.name' => ['required', 'string', 'max:120'],
            'customer.email' => ['required', 'email:rfc', 'max:190'],
            'customer.phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ()\-]{6,30}$/'],
            'flight_number' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9]{2,3}\s?[0-9]{1,4}[A-Z]?$/i'],
            'turnstile_token' => [$turnstile, 'string', 'max:2048', new TurnstileToken()],
        ];
    }

    public function toCreateBookingData(): CreateBookingData
    {
        return new CreateBookingData(
            quoteToken: (string) $this->input('quote_token'),
            vehicleId: (int) $this->input('vehicle_id'),
            paymentMethod: PaymentMethod::from((string) $this->input('payment_method')),
            customerName: trim((string) $this->input('customer.name')),
            customerEmail: mb_strtolower(trim((string) $this->input('customer.email'))),
            customerPhone: trim((string) $this->input('customer.phone')),
            flightNumber: $this->filled('flight_number')
                ? strtoupper(str_replace(' ', '', (string) $this->input('flight_number')))
                : null,
        );
    }
}
