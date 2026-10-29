<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

final class CustomerCancellationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:500']];
    }

    public function reason(): string
    {
        return trim((string) $this->input('reason'));
    }
}
