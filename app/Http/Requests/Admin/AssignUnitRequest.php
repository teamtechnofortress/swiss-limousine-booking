<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class AssignUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['fleet_unit_id' => ['required', 'integer', 'exists:fleet_units,id']];
    }

    public function fleetUnitId(): int
    {
        return (int) $this->input('fleet_unit_id');
    }
}
