<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\DeliveryCenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeliveryCenterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = DeliveryCenter::query();

        if ($search = $request->query('search')) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('owner_name', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($location = $request->query('location')) {
            $query->where('location', 'like', "%{$location}%");
        }

        if (($status = $request->query('status')) !== null) {
            $query->where('is_active', filter_var($status, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE));
        }

        $perPage = (int) $request->query('limit', 25);
        $perPage = max(1, min($perPage, 100));

        $deliveryCenters = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $deliveryCenters->items(),
            'meta' => [
                'page' => $deliveryCenters->currentPage(),
                'limit' => $deliveryCenters->perPage(),
                'total' => $deliveryCenters->total(),
                'pages' => $deliveryCenters->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:500'],
            'mobile_number' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'is_active' => ['boolean'],
        ]);

        $deliveryCenter = DeliveryCenter::create($validated);

        return response()->json(['delivery_center' => $deliveryCenter], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(DeliveryCenter $deliveryCenter): JsonResponse
    {
        return response()->json(['delivery_center' => $deliveryCenter]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DeliveryCenter $deliveryCenter): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'owner_name' => ['sometimes', 'string', 'max:255'],
            'location' => ['sometimes', 'string', 'max:500'],
            'mobile_number' => ['sometimes', 'string', 'regex:/^[6-9]\d{9}$/'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $deliveryCenter->update($validated);

        return response()->json(['delivery_center' => $deliveryCenter]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DeliveryCenter $deliveryCenter): JsonResponse
    {
        $deliveryCenter->delete();

        return response()->json(['delivery_center' => $deliveryCenter]);
    }
}
