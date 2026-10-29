<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RefundPaymentRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentController extends Controller
{
    public function refund(Payment $payment, RefundPaymentRequest $request, PaymentService $payments): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $refund = $payments->refund(
            $payment->booking,
            (int) $request->input('gross_amount'),
            (string) $request->input('reason'),
            $actor,
        );

        return response()->json(['data' => [
            'id' => $refund->id,
            'gross' => $refund->gross_amount,
            'vat' => $refund->vat_amount,
            'status' => $refund->status,
        ]], 201);
    }
}
