<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListEnquiriesRequest;
use App\Http\Requests\Admin\UpdateEnquiryRequest;
use App\Http\Resources\Admin\AdminEnquiryResource;
use App\Models\Enquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class EnquiryController extends Controller
{
    public function index(ListEnquiriesRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $enquiries = Enquiry::query()
            ->with(['trip', 'vehicle', 'booking', 'assignedTo'])
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['trip_id'] ?? null, fn ($query, int $tripId) => $query->where('trip_id', $tripId))
            ->when($filters['vehicle_id'] ?? null, fn ($query, int $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($filters['assigned_to'] ?? null, fn ($query, int $adminId) => $query->where('assigned_to', $adminId))
            ->when($request->boolean('unassigned'), fn ($query) => $query->whereNull('assigned_to'))
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('reference', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('subject_title', 'like', "%{$search}%");
                });
            })
            ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();

        return AdminEnquiryResource::collection($enquiries);
    }

    public function show(Enquiry $enquiry): AdminEnquiryResource
    {
        if ($enquiry->read_at === null) {
            $enquiry->update(['read_at' => now()]);
        }

        return new AdminEnquiryResource($enquiry->load(['trip', 'vehicle', 'booking', 'assignedTo']));
    }

    public function newCount(): JsonResponse
    {
        return response()->json(['data' => [
            'new' => Enquiry::query()->whereNull('read_at')->count(),
        ]]);
    }

    public function update(Enquiry $enquiry, UpdateEnquiryRequest $request): AdminEnquiryResource
    {
        $data = $request->validated();

        if (($data['status'] ?? 'new') !== 'new' && $enquiry->handled_at === null) {
            $data['handled_at'] = now();
        }
        if ($enquiry->read_at === null) {
            $data['read_at'] = now();
        }

        $enquiry->update($data);

        return new AdminEnquiryResource($enquiry->fresh()->load(['trip', 'vehicle', 'booking', 'assignedTo']));
    }
}
