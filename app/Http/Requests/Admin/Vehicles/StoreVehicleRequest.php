<?php

namespace App\Http\Requests\Admin\Vehicles;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreVehicleRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:120'],
            'model_name' => ['nullable', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('vehicles', 'slug')],
            'category_id' => ['required', 'integer', Rule::exists('vehicle_categories', 'id')->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:4000'],
            'passenger_capacity' => ['required', 'integer', 'between:1,20'],
            'luggage_capacity' => ['required', 'integer', 'between:0,30'],
            'price_per_km' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'minimum_fare' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', Rule::in(Vehicle::AMENITIES)],
            'slot_check_enabled' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,65535'],
            'units' => ['nullable', 'array', 'max:50'],
            'units.*' => ['required', 'string', 'max:20'],
        ];
    }
}
