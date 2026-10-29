# Changes made while assembling this overlay

These fix problems in the earlier draft code:

1. `BookingService::create()` called `lockForBooking()`, which does not exist. It now calls `lockVehicle()`.
2. Slot-check-off vehicles no longer get schedule blocks, both at creation and when a late payment is settled.
3. Turnstile fields are `required` when bot checks are on. They were `nullable`, which skipped the check.
4. `Booking::vehicle()` uses `withTrashed()`, so bookings still show their vehicle after it is retired.
5. Settings have a seeder, so a fresh database has every default.
6. `SettingsService::secret()` returns null for an empty secret. Previously it returned an empty string.
7. A `BookingConfirmed` event sends the confirmation email after payment or verification, after the database commit.
8. `BookingCreated` sends the "booking received" email with the access link, and the verification link when required.
9. `StripeGateway` reads `config('limo.frontend_url')` instead of an app config key that did not exist.
10. Settings `required_location_id` uses the `nullable_int` type, which the cast handles.
