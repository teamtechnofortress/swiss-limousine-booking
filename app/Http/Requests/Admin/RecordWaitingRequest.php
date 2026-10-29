<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class RecordWaitingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['minutes' => ['required', 'integer', 'between:0,1440']];
    }

    public function minutes(): int
    {
        return (int) $this->input('minutes');
    }
}
