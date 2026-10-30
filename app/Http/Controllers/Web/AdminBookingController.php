<?php

namespace App\Http\Controllers\Web;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Bookings\BookingAdminService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminBookingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', array_column(BookingStatus::cases(), 'value'))],
            'payment_method' => ['nullable', 'in:'.implode(',', array_column(PaymentMethod::cases(), 'value'))],
            'q' => ['nullable', 'string', 'max:100'],
            'unseen' => ['nullable', 'boolean'],
        ]);

        $bookings = Booking::query()->with(['vehicle', 'fleetUnit', 'payment', 'legs'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['payment_method'] ?? null, fn ($q, $method) => $q->where('payment_method', $method))
            ->when($request->boolean('unseen'), fn ($q) => $q->unseen())
            ->when($filters['q'] ?? null, function ($q, string $term): void {
                $term = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($search) => $search->where('reference', 'like', $term)->orWhere('customer_name', 'like', $term)->orWhere('customer_email', 'like', $term));
            })
            ->adminQueue()
            ->paginate(20)
            ->withQueryString();

        return view('admin.bookings.index', compact('bookings', 'filters'));
    }

    public function show(Booking $booking, BookingAdminService $service): View
    {
        $service->markSeen($booking);
        $booking->load(['vehicle', 'fleetUnit', 'legs', 'payment.refunds', 'adjustments']);
        $units = $booking->vehicle?->fleetUnits()->orderBy('plate')->get() ?? collect();

        return view('admin.bookings.show', compact('booking', 'units'));
    }

    public function assign(Booking $booking, Request $request, BookingAdminService $service): RedirectResponse
    {
        $data = $request->validate(['fleet_unit_id' => ['required', 'integer', 'exists:fleet_units,id']]);
        $service->assignUnit($booking->id, (int) $data['fleet_unit_id']);

        return back()->with('status', 'Fleet unit assigned.');
    }

    public function waiting(Booking $booking, Request $request, BookingAdminService $service): RedirectResponse
    {
        $data = $request->validate(['minutes' => ['required', 'integer', 'min:1', 'max:1440']]);
        $service->recordWaiting($booking->id, (int) $data['minutes'], $request->user());

        return back()->with('status', 'Waiting-time adjustment recorded.');
    }

    public function cancel(Booking $booking, Request $request, BookingAdminService $service): RedirectResponse
    {
        $data = $request->validate([
            'refund_gross' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $service->cancel($booking->id, (int) $data['refund_gross'], $data['reason'], $request->user());

        return redirect()->route('admin.bookings.index')->with('status', 'Booking cancelled.');
    }

    public function markPaid(Booking $booking, Request $request, PaymentService $payments): RedirectResponse
    {
        $payments->markOfflinePaid($booking->load('payment'), $request->user());

        return back()->with('status', 'Offline payment marked as paid.');
    }
}
