<?php

namespace App\Http\Requests\Admin;

use App\Models\Enquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListEnquiriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(Enquiry::TYPES)],
            'status' => ['nullable', Rule::in(Enquiry::STATUSES)],
            'trip_id' => ['nullable', 'integer', 'exists:trips,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'unassigned' => ['nullable', 'boolean'],
            'unread' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:10,100'],
        ];
    }
}
