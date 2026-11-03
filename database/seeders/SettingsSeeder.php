<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Creates every setting with its default. Existing rows are never overwritten, so re-running is safe.
 * Secrets start empty and are set with: php artisan settings:set <key>
 */
final class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $key => $definition) {
            Setting::query()->firstOrCreate(['key' => $key], $definition);
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function definitions(): array
    {
        $integrations = 'integrations.manage';

        return [
            // Integrations (super admin only)
            'stripe_mode' => $this->def('integrations', 'string', 'test', 'Stripe environment: test (no real money) or live.', false, $integrations),
            'stripe_secret_key' => $this->def('integrations', 'secret', '', 'Stripe secret key used on the server. Encrypted.', true, $integrations),
            'stripe_publishable_key' => $this->def('integrations', 'string', '', 'Stripe publishable key, if the frontend loads Stripe.js.', false, $integrations),
            'stripe_webhook_secret' => $this->def('integrations', 'secret', '', 'Signing secret that proves a webhook came from Stripe. Encrypted.', true, $integrations),
            'google_browser_key' => $this->def('integrations', 'string', '', 'Google key for Places Autocomplete in the browser. Restrict to your domains.', false, $integrations),
            'google_server_key' => $this->def('integrations', 'secret', '', 'Google key used by Laravel for Place Details and Routes. Restrict to server IPs. Encrypted.', true, $integrations),
            'turnstile_site_key' => $this->def('integrations', 'string', '', 'Cloudflare Turnstile site key used in the customer browser. This is safe to publish.', false, $integrations),
            'turnstile_secret_key' => $this->def('integrations', 'secret', '', 'Cloudflare Turnstile secret for bot checks. Encrypted.', true, $integrations),
            'mail_mailer' => $this->def('integrations', 'string', 'smtp', 'Email transport: smtp for production, log to write emails to the log file for local testing.', false, $integrations),
            'smtp_host' => $this->def('integrations', 'string', '', 'SMTP server host name, for example smtp.provider.ch.', false, $integrations),
            'smtp_port' => $this->def('integrations', 'int', '587', 'SMTP port: 587 for STARTTLS, 465 for SSL.', false, $integrations),
            'smtp_encryption' => $this->def('integrations', 'string', 'tls', 'Connection security: tls (STARTTLS), ssl or none.', false, $integrations),
            'smtp_username' => $this->def('integrations', 'string', '', 'SMTP login name.', false, $integrations),
            'smtp_password' => $this->def('integrations', 'secret', '', 'SMTP password. Encrypted and never shown after saving.', true, $integrations),

            // Payment methods
            'payment_gateway' => $this->def('payment_methods', 'string', 'stripe', 'Active online payment provider.', false, 'payment_methods.manage'),
            'online_payment_enabled' => $this->def('payment_methods', 'bool', '1', 'Customers can pay by card, Apple Pay or Google Pay through Stripe Checkout.', false, 'payment_methods.manage'),
            'cash_on_arrival_enabled' => $this->def('payment_methods', 'bool', '0', 'Customers can book now and pay the driver in cash.', false, 'payment_methods.manage'),
            'coa_email_verification_enabled' => $this->def('payment_methods', 'bool', '1', 'Cash bookings are confirmed only after the customer clicks an email link.', false, 'payment_methods.manage'),
            'coa_verification_ttl_hours' => $this->def('payment_methods', 'int', '24', 'Hours an unverified cash booking holds its car before it expires.', false, 'payment_methods.manage'),
            'coa_max_amount' => $this->def('payment_methods', 'nullable_money', '', 'Largest booking total accepted as cash, in rappen. Empty means no limit.', false, 'payment_methods.manage'),

            // Service area
            'service_area_enforced' => $this->def('service_area', 'bool', '1', 'When on, every trip must fall inside the allowed countries and the required location rule.', false, 'service_area.manage'),
            'allowed_countries' => $this->def('service_area', 'json', '["CH"]', 'ISO country codes where pickups and drop-offs are allowed.', false, 'service_area.manage'),
            'allowed_country_names' => $this->def('service_area', 'json', '{"CH":"Switzerland"}', 'Country names paired with the allowed country codes for administration display.', false, 'service_area.manage'),
            'required_location_enabled' => $this->def('service_area', 'bool', '0', 'When on, every journey must touch the required location. Enable after choosing the location.', false, 'service_area.manage'),
            'required_location_id' => $this->def('service_area', 'nullable_int', '', 'The saved location a journey must include (from Locations).', false, 'service_area.manage'),
            'required_location_match_type' => $this->def('service_area', 'string', 'radius', 'How an address matches the required location: city, region or radius.', false, 'service_area.manage'),
            'required_location_radius_km' => $this->def('service_area', 'int', '25', 'Radius in km used when the match type is radius.', false, 'service_area.manage'),

            // Locations
            'place_match_radius_m' => $this->def('locations', 'int', '2000', 'An address within this distance of a saved location uses that location\'s stored route.', false, 'locations.manage'),
            'restrict_pickups_enabled' => $this->def('locations', 'bool', '0', 'Master switch: restrict customer pickups to active allowed locations.', false, 'locations.manage'),
            'restricted_pickup_message' => $this->def('locations', 'string', 'Choose an available pickup location to continue.', 'Shown when a customer selects a pickup outside the allowed list.', false, 'locations.manage'),

            // Trips
            'trips_enabled' => $this->def('trips', 'bool', '1', 'Master switch for published day tours and trip bookings.', false, 'trips.manage'),
            'trip_min_notice_hours' => $this->def('trips', 'int', '48', 'Minimum notice before a customer can book a trip departure.', false, 'trips.manage'),

            // Availability
            'default_slot_check_enabled' => $this->def('availability', 'bool', '1', 'Starting value of the slot check for new vehicles.', false, 'fleet.manage'),
            'hold_ttl_minutes' => $this->def('availability', 'int', '31', 'Minutes an online booking holds its car while the customer pays. Minimum 31 (Stripe needs more than 30).', false, 'fleet.manage'),
            'default_buffer_minutes' => $this->def('availability', 'int', '60', 'Minutes reserved before pickup and after drop-off for reset and delays.', false, 'fleet.manage'),

            // Tax and currency
            'currency' => $this->def('tax', 'string', 'CHF', 'Currency for all prices, receipts and invoices.', false, 'tax.manage'),
            'vat_enabled' => $this->def('tax', 'bool', '1', 'Turns VAT calculation on. Turn off only if the company is not VAT-registered.', false, 'tax.manage'),
            'vat_rate_bp' => $this->def('tax', 'int', '810', 'VAT rate in basis points. 810 = 8.10 percent.', false, 'tax.manage'),
            'vat_included' => $this->def('tax', 'bool', '1', 'When on, prices are entered and shown including VAT.', false, 'tax.manage'),
            'vat_registration_number' => $this->def('tax', 'string', '', 'Swiss VAT number printed on receipts (format CHE-xxx.xxx.xxx MWST).', false, 'tax.manage'),
            'invoice_number_prefix' => $this->def('tax', 'string', 'LX', 'Prefix for sequential invoice numbers.', false, 'tax.manage'),

            // Pricing and quotes
            'quote_ttl_minutes' => $this->def('pricing', 'int', '30', 'Minutes a quote stays valid before the customer must search again.', false, 'pricing.manage'),
            'round_trip_enabled' => $this->def('pricing', 'bool', '1', 'Shows the return option on the website.', false, 'pricing.manage'),
            'round_trip_min_return_gap_minutes' => $this->def('pricing', 'int', '120', 'Minimum minutes between the outbound drop-off and the return pickup.', false, 'pricing.manage'),
            'round_trip_discount_percent' => $this->def('pricing', 'int', '0', 'Discount on the round-trip total. 0 means each leg is priced in full.', false, 'pricing.manage'),
            'waiting_free_minutes' => $this->def('pricing', 'int', '15', 'Waiting minutes included before any charge applies.', false, 'pricing.manage'),
            'waiting_block_minutes' => $this->def('pricing', 'int', '15', 'Length of each chargeable waiting block, in minutes.', false, 'pricing.manage'),
            'waiting_rate_per_block' => $this->def('pricing', 'money', '1500', 'Charge per waiting block in rappen (1500 = CHF 15.00). Gross when VAT is included.', false, 'pricing.manage'),

            // Bookings
            'min_lead_time_hours' => $this->def('bookings', 'int', '2', 'Earliest a customer can book before pickup, in hours.', false, 'bookings.view'),
            'max_advance_days' => $this->def('bookings', 'int', '365', 'Furthest ahead a customer can book, in days.', false, 'bookings.view'),
            'cancellation_free_hours' => $this->def('bookings', 'int', '24', 'Cancelling at least this many hours before pickup qualifies for a full refund.', false, 'bookings.cancel'),

            // Quote and booking security
            'turnstile_enabled' => $this->def('security', 'bool', '1', 'Requires a bot check on quote and booking requests. Turn off only in local development.', false, 'integrations.manage'),

            // Company
            'site_name' => $this->def('company', 'string', 'Swiss Limousine', 'Name shown in the public website header, browser title and customer-facing pages.', false, 'company.manage'),
            'site_logo_url' => $this->def('company', 'string', '', 'Public HTTPS URL or site-relative path for the logo shown in the customer website header. Leave blank to use the site name.', false, 'company.manage'),
            'company_legal_name' => $this->def('company', 'string', '', 'Legal name printed on receipts and emails.', false, 'company.manage'),
            'company_email' => $this->def('company', 'string', '', 'Reply-to address and contact on customer emails.', false, 'company.manage'),
            'company_phone' => $this->def('company', 'string', '', 'Phone number shown in emails.', false, 'company.manage'),
            'company_address' => $this->def('company', 'string', '', 'Postal address shown on receipts.', false, 'company.manage'),
            'admin_notification_email' => $this->def('company', 'string', '', 'Receives admin alerts such as new bookings and reviews.', false, 'company.manage'),
            'mail_from_address' => $this->def('company', 'string', '', 'Sender address on customer emails. Must be allowed by the SMTP provider.', false, 'company.manage'),
            'mail_from_name' => $this->def('company', 'string', '', 'Sender name shown on customer emails.', false, 'company.manage'),
        ];
    }

    /** @return array<string, mixed> */
    private function def(string $group, string $type, string $value, string $description, bool $secret, string $editableBy): array
    {
        return [
            'group' => $group,
            'type' => $type,
            'value' => $value === '' && $secret ? null : $value,
            'description' => $description,
            'is_secret' => $secret,
            'editable_by' => $editableBy,
        ];
    }
}
