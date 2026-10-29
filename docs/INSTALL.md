# Limousine booking backend: install guide

This zip contains the complete backend for the booking core and admin access control.
It is an overlay: unzip it over a fresh Laravel 12 project. Files with the same path are replaced.

## 1. Create the Laravel project

```bash
composer create-project laravel/laravel limo
cd limo
```

Requires PHP 8.2 or newer, MySQL 8, Redis, and a queue worker.

## 2. Unzip this overlay into the project root

Unzip so that `app/`, `config/`, `database/`, `routes/`, `bootstrap/`, `tests/` and `docs/` sit directly
inside the project folder. These files replace Laravel defaults:

- `bootstrap/app.php`, `bootstrap/providers.php`
- `app/Models/User.php`, `app/Providers/AppServiceProvider.php`
- `database/seeders/DatabaseSeeder.php`, `routes/api.php`

## 3. Install packages

```bash
composer require laravel/sanctum spatie/laravel-permission spatie/laravel-activitylog \
  spatie/laravel-medialibrary stripe/stripe-php predis/predis
php artisan install:api
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
```

Laravel's default `users` migration must stay in place. The overlay adds columns to it.

## 4. Environment (.env)

```dotenv
APP_TIMEZONE=UTC
FRONTEND_URL=http://localhost:3000
ADMIN_SESSION_MINUTES=480

DB_CONNECTION=mysql
DB_DATABASE=limo
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_CLIENT=predis
SESSION_DRIVER=file

MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=bookings@example.com
MAIL_FROM_NAME="Limousine Booking"
```

`APP_TIMEZONE=UTC` is required. Every time comparison assumes UTC.
Mail settings are server-side on purpose. They are not in the admin panel.

## 5. CORS

The frontend sends the header `X-Access-Token`. In `config/cors.php`, `allowed_headers` must include it
(`['*']` also works). `paths` must include `api/*`.

## 6. Database and seed data

```bash
php artisan migrate
php artisan db:seed
```

The seeder creates:
- roles and permissions from `config/rbac.php`
- every setting with its default (existing values are never overwritten)
- sample vehicles, only when `APP_ENV=local`. The values come from a competitor's public fleet page.
  Replace them with your real fleet before going live.

## 7. Set secrets (never stored in plain text)

```bash
php artisan settings:set stripe_secret_key
php artisan settings:set stripe_webhook_secret
php artisan settings:set google_server_key
php artisan settings:set google_browser_key
php artisan settings:set turnstile_secret_key
php artisan settings:set stripe_mode test
```

Each command prompts for the value, so it is not kept in shell history. Use `test` keys until go-live.

Turn on the Turnstile check only after its keys are set. Before that, set `turnstile_enabled` to `0` for local work:

```bash
php artisan settings:set turnstile_enabled 0
```

## 8. Create the first super admin

```bash
php artisan admin:create owner@example.com "Owner Name" --role=super_admin
```

## 9. Run the background processes

```bash
php artisan queue:work redis --queue=default,mail
php artisan schedule:work
```

- The queue worker sends emails and processes Stripe webhooks.
- `schedule:work` expires unpaid holds every minute. In production, run `schedule:run` from cron instead.

## 10. Stripe webhooks (local)

```bash
stripe listen --forward-to localhost:8000/api/webhooks/payments/stripe
```

Paste the signing secret that the command prints into `settings:set stripe_webhook_secret`.

## 11. Run the tests

```bash
php artisan test
```

## What is in this overlay

| Area | Contents |
|---|---|
| Quotes and pricing | Redis quotes with 30 minute TTL, per-km pricing with minimum fare, round trip, VAT split |
| Service area | Allowed countries, required location (city, region or radius) |
| Availability | Slot holds under a vehicle row lock, buffers, expiry, slot check per vehicle |
| Bookings | Online and cash flows, verification link, late payment handling, admin review, assignment, waiting time, cancellation with refund |
| Payments | Stripe Checkout behind a gateway interface, signed webhooks stored and processed once, refunds capped and idempotent |
| Settings | Database settings with defaults, encrypted secrets, audit log on every change |
| Access control | Roles and permissions from `config/rbac.php`, admin login with tokens, deactivation revokes tokens |
| Emails | Booking received (with access link), cash verification link, payment confirmed. Plain text for now |

Read `docs/RBAC.md` for admin access rules.

## Not in this overlay yet

These are designed in the specification but not built in this package. Each needs its own batch:

- Admin screens for vehicles, fleet units, locations, routes, service areas, payment methods,
  integrations, tax, company details, notification templates, the settings API, the dashboard,
  the payment list and reports. Until these exist, create vehicles, units, locations and routes with
  `php artisan tinker` or the local seeder.
- Scheduled reminders before pickup, and emails when a hold expires.
- Branded email templates (currently plain text, built in `BookingMailer`).
- The concurrency test suite for the last-unit race. It needs a MySQL test database.
- The two-factor login check for `super_admin` and `finance` (the roles are listed in config).
- Vehicle image upload endpoints (Media Library is installed; the admin endpoints are not built).

## Important notes

- Nothing in this package has been executed yet. Run `php artisan test` before you rely on it.
- The Google Routes cache is 30 days. Check Google's current caching terms before you keep it longer.
- Emails contain the access link. Anyone with the email can manage that booking, so keep mail delivery secure.
