# Admin RBAC

## Model

- Customers have no accounts. Only admin users are rows in `users`.
- Permissions are stable keys such as `bookings.view`. Routes and gates use them.
- Roles group permissions. The default roles are super_admin, manager, dispatcher, finance and viewer.
- `config/rbac.php` is the source of truth. `php artisan rbac:sync` writes it to the database.
- Roles and permissions are stored in Spatie tables, using the `sanctum` guard.

## Setup

```bash
composer require spatie/laravel-permission spatie/laravel-activitylog laravel/sanctum
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan install:api
php artisan migrate
php artisan rbac:sync
php artisan admin:create owner@example.com "Owner Name" --role=super_admin
```

Add `ADMIN_SESSION_MINUTES=480` to `.env` if you want a different session length.

## Adding a permission

1. Add the key and label under a group in `config/rbac.php`.
2. Grant it to the roles that need it.
3. Run `php artisan rbac:sync`.
4. Use it on a route: `->middleware('can:your.permission')`.

## Adding a role

1. Add an entry under `roles` with a label, description and permission list.
2. Run `php artisan rbac:sync`.
3. Assign it to users through the admin API.

## Rules enforced by the code

- At least one active super admin always exists.
- Nobody changes their own roles or status.
- Only a super admin can grant super_admin.
- Deactivation revokes every token immediately.
- Password changes revoke every token for that account.
- Login returns the same error for every failure, so accounts cannot be enumerated.
- Login is rate limited per IP and email.

## Not yet built

- Two-factor login. The roles that need it are listed in config, but the check is not enforced yet.
- A UI for editing role grants. Until then, grants change through config only.
- Record-level policies, such as limiting a dispatcher to certain vehicles. Add a policy when a record-level rule is needed.

## Important

`rbac:sync` must run before the admin API is used. Until it has run, gates for permissions that are not in the database will fail.
