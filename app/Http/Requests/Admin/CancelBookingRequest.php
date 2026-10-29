<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class CancelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'refund_gross' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function refundGross(): int
    {
        return (int) $this->input('refund_gross');
    }

    public function reason(): string
    {
        return trim((string) $this->input('reason'));
    }
}
