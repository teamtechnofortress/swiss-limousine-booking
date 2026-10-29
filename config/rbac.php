<?php

/*
|--------------------------------------------------------------------------
| Role-Based Access Control (admin users)
|--------------------------------------------------------------------------
|
| Source of truth for admin permissions.
|
| - permissions: stable keys used by routes (can:bookings.view) and gates.
|   Labels and groups feed the admin UI.
| - roles: each role lists its permissions, or ['*'] for every permission.
| - After any change here, run: php artisan rbac:sync
|
| Customers are not users in this system and never appear here.
|
*/

return [

    'guard' => 'sanctum',

    /* Absolute lifetime of an admin API token, in minutes. */
    'session_minutes' => (int) env('ADMIN_SESSION_MINUTES', 480),

    'permissions' => [
        'dashboard' => [
            'dashboard.view' => 'View dashboard',
        ],
        'bookings' => [
            'bookings.view' => 'View bookings',
            'bookings.edit' => 'Edit booking details',
            'bookings.assign' => 'Assign vehicle units',
            'bookings.create_manual' => 'Create manual bookings',
            'bookings.cancel' => 'Cancel bookings',
            'bookings.waiting' => 'Record waiting time',
        ],
        'payments' => [
            'payments.view' => 'View payments',
            'payments.mark_paid' => 'Mark offline payments as paid',
            'payments.refund' => 'Issue refunds',
        ],
        'fleet' => [
            'fleet.manage' => 'Manage vehicles and fleet units',
        ],
        'locations' => [
            'locations.manage' => 'Manage locations',
            'routes.manage' => 'Manage routes',
        ],
        'settings' => [
            'service_area.manage' => 'Manage service area',
            'payment_methods.manage' => 'Manage payment methods',
            'pricing.manage' => 'Manage pricing and quote rules',
            'tax.manage' => 'Manage tax and VAT settings',
            'company.manage' => 'Manage company details',
            'notifications.manage' => 'Manage notification templates',
            'integrations.view' => 'View integrations',
            'integrations.manage' => 'Manage integrations and credentials',
        ],
        'reports' => [
            'reports.tax.view' => 'View and export tax reports',
            'audit.view' => 'View audit log',
        ],
        'administration' => [
            'users.manage' => 'Manage admin users and roles',
        ],
    ],

    'roles' => [
        'super_admin' => [
            'label' => 'Super admin',
            'description' => 'Full access, including credentials and admin users. Keep this role to a minimum.',
            'permissions' => ['*'],
        ],
        'manager' => [
            'label' => 'Manager',
            'description' => 'Runs day-to-day operations: bookings, fleet, pricing, service area and refunds.',
            'permissions' => [
                'dashboard.view',
                'bookings.view', 'bookings.edit', 'bookings.assign', 'bookings.create_manual',
                'bookings.cancel', 'bookings.waiting',
                'payments.view', 'payments.mark_paid', 'payments.refund',
                'fleet.manage', 'locations.manage', 'routes.manage', 'service_area.manage',
                'payment_methods.manage', 'pricing.manage',
                'reports.tax.view', 'company.manage', 'notifications.manage', 'audit.view',
            ],
        ],
        'dispatcher' => [
            'label' => 'Dispatcher',
            'description' => 'Handles bookings and assigns vehicles. No money actions.',
            'permissions' => [
                'dashboard.view',
                'bookings.view', 'bookings.edit', 'bookings.assign', 'bookings.create_manual', 'bookings.waiting',
            ],
        ],
        'finance' => [
            'label' => 'Finance',
            'description' => 'Payments, refunds, waiting charges and tax reports.',
            'permissions' => [
                'dashboard.view', 'bookings.view', 'bookings.waiting',
                'payments.view', 'payments.mark_paid', 'payments.refund',
                'reports.tax.view',
            ],
        ],
        'viewer' => [
            'label' => 'Viewer',
            'description' => 'Read-only access to bookings, payments and the dashboard.',
            'permissions' => ['dashboard.view', 'bookings.view', 'payments.view'],
        ],
    ],

    /*
    | Roles that must use two-factor login. Recorded now and enforced once
    | two-factor login is built. Kept in code review, not the admin UI,
    | because it is a security policy.
    */
    'two_factor_required_roles' => ['super_admin', 'finance'],
];
