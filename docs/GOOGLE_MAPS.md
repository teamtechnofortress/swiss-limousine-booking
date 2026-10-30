# Google Maps data handling

The booking API uses Google only to validate submitted Place IDs and calculate a live route for a
quote. It does not use a shared cache for Place Details or Routes responses.

## Frontend contract

The booking form must use a compliant Google Places Autocomplete integration and display the
required Google Maps attribution. Each pickup and drop-off field has a separate autocomplete
session token. When creating a quote, send:

```json
{
  "pickup_place_id": "...",
  "dropoff_place_id": "...",
  "pickup_address": "Customer-confirmed pickup address",
  "dropoff_address": "Customer-confirmed drop-off address",
  "pickup_session_token": "...",
  "dropoff_session_token": "..."
}
```

`pickup_address` and `dropoff_address` are customer-confirmed business data. The backend does
not copy Google formatted addresses, coordinates, or address components into quotes or bookings.
Only the Place ID is retained as an external routing reference.

## Operational rules

- A return trip always calculates its reverse route separately; road distance and duration are not
  assumed to be symmetric.
- Route distance and duration are used only to price and reserve the booked journey. They are not
  returned by the public API as standalone Routes content.
- Dispatch should obtain a fresh navigation route close to pickup. The quote route is
  traffic-unaware and is not a live ETA.
- Do not populate the `locations` table with Google-derived address content without a separate
  legal/compliance decision. Refresh retained Place IDs when they are older than 12 months.

Before launch, review the current Google Maps Platform agreement, Places/Routes policy, required
attribution, and the Privacy Policy with counsel for the business's billing region.
