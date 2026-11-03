<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Setting;
use App\Services\Notifications\MailSettingsService;
use App\Services\Geo\GoogleMapsClient;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

final class AdminSettingsController extends Controller
{
    /** @var array<string, string> */
    private const GROUP_LABELS = [
        'integrations' => 'Integrations',
        'payment_methods' => 'Payment methods',
        'service_area' => 'Service area',
        'locations' => 'Locations & routes',
        'availability' => 'Availability',
        'tax' => 'Tax & currency',
        'pricing' => 'Pricing & quotes',
        'bookings' => 'Booking rules',
        'security' => 'Security',
        'company' => 'Company & notifications',
    ];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly MailSettingsService $mailService,
    ) {}

    public function index(Request $request): View
    {
        $groups = Setting::query()->selectRaw('`group`, count(*) as count')
            ->groupBy('group')->pluck('count', 'group');

        return view('admin.settings.index', [
            'groups' => collect(self::GROUP_LABELS)->map(fn (string $label, string $group) => [
                'key' => $group,
                'label' => $label,
                'count' => (int) ($groups[$group] ?? 0),
                'accessible' => $group === 'integrations'
                    ? $request->user()->can('integrations.view')
                    : Setting::query()->where('group', $group)->get()->contains(fn (Setting $setting) => $request->user()->can($setting->editable_by)),
            ])->filter(fn (array $group) => $group['count'] > 0)->values(),
        ]);
    }

    public function show(Request $request, string $group): View
    {
        $this->assertKnownGroup($group);
        if ($group === 'integrations') {
            abort_unless($request->user()->can('integrations.view'), 403);
        }
        $settings = Setting::query()->where('group', $group)->orderBy('key')->get();
        abort_if($settings->isEmpty(), 404);

        if ($group === 'service_area') {
            $order = [
                'service_area_enforced' => 0,
                'allowed_countries' => 10,
                'required_location_enabled' => 20,
                'required_location_id' => 30,
                'required_location_match_type' => 40,
                'required_location_radius_km' => 50,
                'allowed_country_names' => 60,
            ];
            $settings = $settings->sortBy(fn (Setting $setting): int => $order[$setting->key] ?? 100)->values();
        }
        if ($group === 'company') {
            $order = ['site_name' => 0, 'site_logo_url' => 10];
            $settings = $settings->sortBy(fn (Setting $setting): int => $order[$setting->key] ?? 100)->values();
        }

        return view('admin.settings.show', [
            'group' => $group,
            'title' => self::GROUP_LABELS[$group],
            'settings' => $settings,
            'values' => $this->settings->all(),
            'canEdit' => $settings->every(fn (Setting $setting) => $request->user()->can($setting->editable_by)),
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        $this->assertKnownGroup($group);
        $records = Setting::query()->where('group', $group)->orderBy('key')->get();
        abort_if($records->isEmpty(), 404);
        abort_unless($records->every(fn (Setting $setting) => $request->user()->can($setting->editable_by)), 403);

        $input = $request->input('settings', []);
        if (! is_array($input)) {
            throw ValidationException::withMessages(['settings' => 'Invalid settings submission.']);
        }

        $normalised = [];
        foreach ($records as $setting) {
            $raw = $input[$setting->key] ?? null;
            $normalised[$setting->key] = $this->normalise($setting, $raw);
        }
        $this->assertBusinessRules($group, $normalised);

        foreach ($records as $setting) {
            // Leaving a masked secret blank preserves the existing value.
            if ($setting->is_secret && ($normalised[$setting->key] === null || $normalised[$setting->key] === '')) {
                continue;
            }
            $this->settings->update($setting->key, $normalised[$setting->key], $request->user());
        }

        return back()->with('status', self::GROUP_LABELS[$group].' settings saved.');
    }

    public function searchCountries(Request $request, GoogleMapsClient $maps): JsonResponse
    {
        $data = $request->validate(['query' => ['required', 'string', 'min:2', 'max:120']]);

        return response()->json(['data' => $maps->countryPredictions($data['query'])]);
    }

    public function resolveCountry(string $placeId, GoogleMapsClient $maps): JsonResponse
    {
        $country = $maps->countryForPlace($placeId);
        abort_if($country === null, 422, 'Please select a country from the search results.');

        return response()->json(['data' => $country]);
    }

    public function mail(): View
    {
        return view('admin.settings.mail', [
            'mail' => [
                'mailer' => $this->settings->string('mail_mailer'),
                'host' => $this->settings->string('smtp_host'),
                'port' => $this->settings->int('smtp_port'),
                'encryption' => $this->settings->string('smtp_encryption'),
                'username' => $this->settings->string('smtp_username'),
                'password_set' => $this->settings->secret('smtp_password') !== null,
            ],
            'sender' => [
                'from_address' => $this->settings->string('mail_from_address'),
                'from_name' => $this->settings->string('mail_from_name'),
            ],
        ]);
    }

    public function updateMail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mailer' => ['required', 'in:smtp,log'],
            'host' => ['nullable', 'string', 'max:190'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'encryption' => ['required', 'in:tls,ssl,none'],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);
        foreach (['mailer' => 'mail_mailer', 'host' => 'smtp_host', 'port' => 'smtp_port', 'encryption' => 'smtp_encryption', 'username' => 'smtp_username'] as $field => $key) {
            $this->settings->update($key, (string) ($data[$field] ?? ''), $request->user());
        }
        if (! empty($data['password'])) {
            $this->settings->update('smtp_password', $data['password'], $request->user());
        }

        return back()->with('status', 'Mail delivery settings saved.');
    }

    public function updateSender(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_address' => ['required', 'email:rfc', 'max:190'],
            'from_name' => ['required', 'string', 'max:120'],
        ]);
        $this->settings->update('mail_from_address', $data['from_address'], $request->user());
        $this->settings->update('mail_from_name', $data['from_name'], $request->user());

        return back()->with('status', 'Sender identity saved.');
    }

    public function testMail(Request $request): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'email:rfc', 'max:190']]);
        try {
            $this->mailService->send($data['to'], 'Test email from Swiss Limousine', 'Your mail delivery settings are working.');
        } catch (Throwable $e) {
            return back()->withErrors(['mail' => Str::limit($e->getMessage(), 300)]);
        }

        return back()->with('status', 'Test email sent.');
    }

    private function assertKnownGroup(string $group): void
    {
        abort_unless(array_key_exists($group, self::GROUP_LABELS), 404);
    }

    private function normalise(Setting $setting, mixed $value): mixed
    {
        // ConvertEmptyStringsToNull turns blank password/API-key inputs into null.
        // A blank secret means “keep the encrypted value already saved”.
        if ($setting->is_secret && ($value === null || $value === '')) {
            return null;
        }

        if ($setting->key === 'allowed_countries') {
            return $this->countryCodes($this->jsonValue($setting->key, $value));
        }

        if ($setting->key === 'allowed_country_names') {
            return $this->countryNames($this->jsonValue($setting->key, $value));
        }

        if ($setting->key === 'site_logo_url') {
            $url = trim((string) ($value ?? ''));
            if ($url !== '' && ! str_starts_with($url, '/') && filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw ValidationException::withMessages(['settings.site_logo_url' => 'Enter a valid public URL or a site-relative path beginning with /.']);
            }

            return $url;
        }

        return match ($setting->type) {
            'bool' => $value === '1' || $value === 1 || $value === true,
            'int', 'money' => filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : throw ValidationException::withMessages([$setting->key => 'Enter a whole number.']),
            'nullable_int', 'nullable_money' => $value === null || $value === '' ? null : (filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : throw ValidationException::withMessages([$setting->key => 'Enter a whole number.'])),
            'json' => $this->jsonValue($setting->key, $value),
            // Plain settings such as SMTP host and browser keys are optional.
            default => $value === null ? '' : (is_string($value) || is_numeric($value) ? trim((string) $value) : throw ValidationException::withMessages([$setting->key => 'Enter a valid value.'])),
        };
    }

    private function jsonValue(string $key, mixed $value): array
    {
        $decoded = json_decode((string) $value, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([$key => 'Enter a JSON array.']);
        }

        return $decoded;
    }

    /** @param array<mixed> $countries @return list<string> */
    private function countryCodes(array $countries): array
    {
        if (! array_is_list($countries)) {
            throw ValidationException::withMessages(['settings.allowed_countries' => 'Select countries from the search results.']);
        }

        $codes = array_map(fn (mixed $code): string => strtoupper(trim((string) $code)), $countries);

        if (count($codes) !== count(array_unique($codes)) || collect($codes)->contains(fn (string $code): bool => ! preg_match('/^[A-Z]{2}$/', $code))) {
            throw ValidationException::withMessages(['settings.allowed_countries' => 'Select valid countries from the search results.']);
        }

        sort($codes);

        return $codes;
    }

    /** @param array<mixed> $names @return array<string, string> */
    private function countryNames(array $names): array
    {
        if (array_is_list($names)) {
            throw ValidationException::withMessages(['settings.allowed_country_names' => 'Country names must be saved with their country code.']);
        }

        $normalised = [];
        foreach ($names as $code => $name) {
            $code = strtoupper(trim((string) $code));
            $name = trim((string) $name);
            if (! preg_match('/^[A-Z]{2}$/', $code) || $name === '' || mb_strlen($name) > 120) {
                throw ValidationException::withMessages(['settings.allowed_country_names' => 'Each selected country must have a valid name and code.']);
            }
            $normalised[$code] = $name;
        }
        ksort($normalised);

        return $normalised;
    }

    /** @param array<string, mixed> $values */
    private function assertBusinessRules(string $group, array $values): void
    {
        if ($group === 'service_area') {
            $codes = $values['allowed_countries'] ?? [];
            $names = $values['allowed_country_names'] ?? [];

            if (array_keys($names) !== $codes) {
                throw ValidationException::withMessages(['settings.allowed_country_names' => 'Each allowed country must have a matching saved name.']);
            }

            if (! empty($values['service_area_enforced']) && $codes === []) {
                throw ValidationException::withMessages(['settings.allowed_countries' => 'Select at least one allowed country before enforcing the service area.']);
            }

            if (! empty($values['service_area_enforced']) && ! empty($values['required_location_enabled'])) {
                if (($values['required_location_id'] ?? null) === null) {
                    throw ValidationException::withMessages(['settings.required_location_id' => 'Choose a saved required location before enabling this rule.']);
                }

                if (($values['required_location_match_type'] ?? null) === 'radius' && (int) ($values['required_location_radius_km'] ?? 0) < 1) {
                    throw ValidationException::withMessages(['settings.required_location_radius_km' => 'Enter a radius of at least 1 km.']);
                }
            }
        }

        if ($group === 'payment_methods' && empty($values['online_payment_enabled']) && empty($values['cash_on_arrival_enabled'])) {
            throw ValidationException::withMessages(['settings.online_payment_enabled' => 'Keep at least one customer payment method enabled.']);
        }
        if ($group === 'availability' && (int) $values['hold_ttl_minutes'] < 31) {
            throw ValidationException::withMessages(['settings.hold_ttl_minutes' => 'Stripe Checkout requires a hold of at least 31 minutes.']);
        }
        if ($group === 'tax' && ! empty($values['vat_enabled']) && empty($values['vat_registration_number'])) {
            throw ValidationException::withMessages(['settings.vat_registration_number' => 'A VAT registration number is required when VAT is enabled.']);
        }
        if ($group === 'integrations' && ($values['stripe_mode'] ?? null) !== $this->settings->string('stripe_mode') && Booking::query()->where('status', 'pending_payment')->exists()) {
            throw ValidationException::withMessages(['settings.stripe_mode' => 'Stripe mode cannot change while bookings are awaiting payment.']);
        }
    }
}
