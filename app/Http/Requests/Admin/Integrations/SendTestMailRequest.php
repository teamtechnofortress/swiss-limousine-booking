<?php

namespace App\Http\Requests\Admin\Integrations;

use Illuminate\Foundation\Http\FormRequest;

final class SendTestMailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['to' => ['required', 'email:rfc', 'max:190']];
    }
}
