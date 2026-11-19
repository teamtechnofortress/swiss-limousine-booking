<?php

namespace App\Http\Controllers\Web;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', 'in:'.implode(',', array_column(PaymentStatus::cases(), 'value'))]])['status'] ?? null;
        $payments = Payment::query()->with(['booking.vehicle', 'refunds'])->latest()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->paginate(20)
            ->withQueryString();

        return view('admin.payments.index', compact('payments', 'status'));
    }

    public function refund(Payment $payment, Request $request, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'gross_amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
            'cancel_booking_on_success' => ['nullable', 'boolean'],
        ]);
        $payments->refund(
            $payment->booking,
            (int) $data['gross_amount'],
            $data['reason'],
            $request->user(),
            (bool) ($data['cancel_booking_on_success'] ?? false),
        );

        return back()->with('status', 'Refund requested. Any selected booking cancellation happens only after Stripe confirms the refund by webhook.');
    }
}
