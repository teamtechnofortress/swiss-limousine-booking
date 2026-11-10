<?php

namespace App\Http\Requests\Admin;

use App\Models\Enquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::in(Enquiry::STATUSES)],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'internal_notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }
}
