<?php

namespace App\Http\Requests\Admin\Integrations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSmtpSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'mailer' => ['required', Rule::in(['smtp', 'log'])],
            'host' => [Rule::requiredIf(fn (): bool => $this->input('mailer') === 'smtp'), 'nullable', 'string', 'max:190'],
            'port' => [Rule::requiredIf(fn (): bool => $this->input('mailer') === 'smtp'), 'nullable', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'username' => ['nullable', 'string', 'max:190'],
            // Empty means "keep the saved password". Only a non-empty value replaces it.
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
