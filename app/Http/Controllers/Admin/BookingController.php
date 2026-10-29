<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignUnitRequest;
use App\Http\Requests\Admin\CancelBookingRequest;
use App\Http\Requests\Admin\ListBookingsRequest;
use App\Http\Requests\Admin\RecordWaitingRequest;
use App\Http\Resources\Admin\AdminBookingResource;
use App\Models\Booking;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Bookings\BookingAdminService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class BookingController extends Controller
{
    public function index(ListBookingsRequest $request, BookingRepositoryInterface $bookings): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return AdminBookingResource::collection(
            $bookings->paginateForAdmin($filters, (int) ($filters['per_page'] ?? 25)),
        );
    }

    public function newCount(BookingRepositoryInterface $bookings): JsonResponse
    {
        return response()->json(['data' => ['new' => $bookings->countUnseen()]]);
    }

    public function show(Booking $booking, BookingAdminService $admin): AdminBookingResource
    {
        $admin->markSeen($booking);

        return new AdminBookingResource($booking->load(['vehicle', 'fleetUnit', 'legs', 'payment.refunds', 'adjustments']));
    }

    public function assign(Booking $booking, AssignUnitRequest $request, BookingAdminService $admin): AdminBookingResource
    {
        return new AdminBookingResource($admin->assignUnit($booking->id, $request->fleetUnitId()));
    }

    public function waiting(Booking $booking, RecordWaitingRequest $request, BookingAdminService $admin): JsonResponse
    {
        $adjustment = $admin->recordWaiting($booking->id, $request->minutes(), $this->actor($request));

        return response()->json(['data' => [
            'id' => $adjustment->id,
            'minutes' => $adjustment->minutes,
            'gross' => $adjustment->gross_amount,
            'vat' => $adjustment->vat_amount,
            'status' => $adjustment->status,
        ]], 201);
    }

    public function cancel(Booking $booking, CancelBookingRequest $request, BookingAdminService $admin): AdminBookingResource
    {
        $cancelled = $admin->cancel($booking->id, $request->refundGross(), $request->reason(), $this->actor($request));

        return new AdminBookingResource($cancelled);
    }

    public function markPaid(Booking $booking, Request $request, PaymentService $payments): JsonResponse
    {
        $payment = $payments->markOfflinePaid($booking->load('payment'), $this->actor($request));

        return response()->json(['data' => [
            'status' => $payment->status->value,
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ]]);
    }

    private function actor(Request $request): User
    {
        /** @var User $actor */
        $actor = $request->user();

        return $actor;
    }
}
