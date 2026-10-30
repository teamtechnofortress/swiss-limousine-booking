<?php

namespace App\Http\Controllers\Public;

use App\Enums\PaymentMethod;
use App\Events\BookingCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\CustomerCancellationRequest;
use App\Http\Requests\Public\StoreBookingRequest;
use App\Http\Resources\Public\PublicBookingResource;
use App\Services\Bookings\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

final class BookingController extends Controller
{
    public function store(StoreBookingRequest $request, BookingService $bookings): JsonResponse
    {
        $created = $bookings->create($request->toCreateBookingData());
        $booking = $created->booking;
        $checkoutUrl = null;

        if ($booking->payment_method === PaymentMethod::Online) {
            try {
                $checkoutUrl = $bookings->startOnlineCheckout($booking->id)->url;
            } catch (Throwable $e) {
                // The booking and its hold exist. The customer can retry from the checkout endpoint.
                Log::error('Stripe checkout could not be created', [
                    'reference' => $booking->reference,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($created->wasCreated) {
            event(new BookingCreated($booking, $created->accessToken, $created->verificationToken));
        }

        return (new PublicBookingResource($booking->loadMissing(['legs', 'payment', 'vehicle'])))
            ->additional(['meta' => [
                // Shown once, to the browser that created the booking. Also sent by email.
                'access_token' => $created->accessToken,
                'checkout_url' => $checkoutUrl,
            ]])
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $reference, Request $request, BookingService $bookings): PublicBookingResource
    {
        $booking = $bookings->findForCustomer($reference, (string) $request->header('X-Access-Token'));

        return new PublicBookingResource($booking->loadMissing(['legs', 'payment', 'vehicle']));
    }

    public function checkout(string $reference, Request $request, BookingService $bookings): JsonResponse
    {
        $booking = $bookings->findForCustomer($reference, (string) $request->header('X-Access-Token'));

        return response()->json(['data' => ['checkout_url' => $bookings->startOnlineCheckout($booking->id)->url]]);
    }

    public function cancelRequest(string $reference, CustomerCancellationRequest $request, BookingService $bookings): JsonResponse
    {
        $booking = $bookings->findForCustomer($reference, (string) $request->header('X-Access-Token'));
        $bookings->requestCancellation($booking, $request->reason());

        return response()->json([
            'data' => ['message' => 'Your cancellation request has been received. We will contact you shortly.'],
        ], 202);
    }

    public function verify(string $token, BookingService $bookings): PublicBookingResource
    {
        $booking = $bookings->verifyCashOnArrival($token);

        return new PublicBookingResource($booking->loadMissing(['legs', 'payment', 'vehicle']));
    }
}
