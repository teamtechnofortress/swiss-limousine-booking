<?php

namespace App\Http\Requests\Public;

use App\Rules\TurnstileToken;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class StoreTripQuoteRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $turnstile = app(SettingsService::class)->bool('turnstile_enabled') ? 'required' : 'nullable';
        return ['travel_date' => ['required','date'], 'departure_id' => ['nullable','integer'], 'custom_departure_time' => ['nullable','date_format:H:i'], 'travellers' => ['required','integer','min:1','max:99'], 'pickup_place_id' => ['required','string','max:255'], 'pickup_address' => ['required','string','min:3','max:255'], 'addons' => ['nullable','array'], 'addons.*' => ['integer','min:0','max:20'], 'turnstile_token' => [$turnstile, 'string','max:2048',new TurnstileToken]];
    }
    public function travelDate(): CarbonImmutable { return CarbonImmutable::parse((string) $this->input('travel_date'), 'Europe/Zurich')->startOfDay(); }
    /** @return array<int,int> */
    public function addons(): array { return collect($this->input('addons', []))->mapWithKeys(fn ($quantity, $id) => [(int) $id => (int) $quantity])->all(); }
}
