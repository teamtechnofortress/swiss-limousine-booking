<?php

namespace App\Http\Requests\Admin\Vehicles;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateVehicleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('units_text')) {
            $this->merge(['units' => array_values(array_filter(preg_split('/[\r\n,]+/', (string) $this->input('units_text')) ?: [], fn (string $plate) => trim($plate) !== ''))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('fleet.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Vehicle $vehicle */
        $vehicle = $this->route('vehicle');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'model_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'slug' => ['sometimes', 'required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('vehicles', 'slug')->ignore($vehicle)],
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists('vehicle_categories', 'id')],
            'description' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'passenger_capacity' => ['sometimes', 'required', 'integer', 'between:1,20'],
            'luggage_capacity' => ['sometimes', 'required', 'integer', 'between:0,30'],
            'price_per_km' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000000'],
            'minimum_fare' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000'],
            'amenities' => ['sometimes', 'nullable', 'array'],
            'amenities.*' => ['string', Rule::in(Vehicle::AMENITIES)],
            'slot_check_enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'required', 'integer', 'between:0,65535'],
            'is_active' => ['sometimes', 'boolean'],
            'units' => ['sometimes', 'array', 'max:50'],
            'units.*' => ['required', 'string', 'max:20'],
        ];
    }
}
