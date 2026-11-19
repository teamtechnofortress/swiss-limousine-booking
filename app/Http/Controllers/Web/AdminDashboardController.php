<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Vehicle;
use App\Services\Admin\FinancialReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminDashboardController extends Controller
{
    public function index(Request $request, FinancialReportService $financialReports): View
    {
        $user = $request->user();
        $today = CarbonImmutable::today('Europe/Zurich');

        return view('admin.dashboard', [
            'stats' => [
                'new' => Booking::query()->unseen()->count(),
                'today' => Booking::query()->whereHas('legs', fn ($q) => $q->where('direction', 'outbound')->whereBetween('starts_at', [$today->utc(), $today->endOfDay()->utc()]))->count(),
            ],
            'recentBookings' => Booking::query()->with(['vehicle', 'payment', 'legs'])->adminQueue()->limit(7)->get(),
            'paymentSummary' => Payment::query()->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status'),
            'vehicleCount' => Vehicle::query()->where('is_active', true)->count(),
            'financialOverview' => $financialReports->overview(),
            'user' => $user,
        ]);
    }

    public function refreshFinancialOverview(FinancialReportService $financialReports): RedirectResponse
    {
        $financialReports->refresh();

        return back()->with('status', 'Financial overview refreshed from the database.');
    }

    public function workspace(Request $request, string $module): View
    {
        $labels = [
            'fleet' => 'Fleet & vehicles',
            'locations' => 'Locations & routes',
            'reports' => 'Reports',
            'audit' => 'Audit log',
            'notifications' => 'Notifications',
        ];

        abort_unless(array_key_exists($module, $labels), 404);

        $permissions = [
            'fleet' => 'fleet.manage',
            'locations' => 'locations.manage',
            'reports' => 'reports.tax.view',
            'audit' => 'audit.view',
            'notifications' => 'notifications.manage',
        ];
        abort_unless($request->user()->can($permissions[$module]), 403);

        return view('admin.workspace', ['module' => $module, 'title' => $labels[$module]]);
    }
}
