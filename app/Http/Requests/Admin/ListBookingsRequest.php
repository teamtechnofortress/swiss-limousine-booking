<?php

namespace App\Http\Requests\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListBookingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The route's can:bookings.view gate decides access.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(array_column(BookingStatus::cases(), 'value'))],
            'source' => ['nullable', Rule::in(['website', 'manual'])],
            'payment_method' => ['nullable', Rule::in(array_column(PaymentMethod::cases(), 'value'))],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:100'],
            'unseen' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'between:10,100'],
        ];
    }
}
