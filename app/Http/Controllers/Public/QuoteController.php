<?php

namespace App\Http\Controllers\Public;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreQuoteRequest;
use App\Http\Resources\QuoteResource;
use App\Services\Quotes\QuoteService;
use App\Services\Quotes\QuoteStore;
use Illuminate\Http\JsonResponse;

final class QuoteController extends Controller
{
    public function store(StoreQuoteRequest $request, QuoteService $quotes): JsonResponse
    {
        $snapshot = $quotes->create($request->toQuoteRequest());

        return (new QuoteResource($snapshot))->response()->setStatusCode(201);
    }

    public function show(string $token, QuoteStore $store): QuoteResource
    {
        $snapshot = $store->find($token) ?? throw BookingException::quoteUnavailable();

        return new QuoteResource($snapshot);
    }
}
