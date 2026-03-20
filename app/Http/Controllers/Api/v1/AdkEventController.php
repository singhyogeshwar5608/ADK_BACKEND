<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdkEvent\AdkEventIndexRequest;
use App\Http\Requests\AdkEvent\StoreAdkEventRequest;
use App\Http\Requests\AdkEvent\UpdateAdkEventRequest;
use App\Http\Resources\AdkEventResource;
use App\Models\AdkEvent;
use Illuminate\Http\JsonResponse;

class AdkEventController extends Controller
{
    public function index(AdkEventIndexRequest $request): JsonResponse
    {
        $query = AdkEvent::query();

        if ($search = $request->input('search')) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('leader_name', 'LIKE', "%{$search}%")
                    ->orWhere('state', 'LIKE', "%{$search}%")
                    ->orWhere('city', 'LIKE', "%{$search}%");
            });
        }

        if ($startDate = $request->input('start_date')) {
            $query->whereDate('meeting_date', '>=', $startDate);
        }

        if ($endDate = $request->input('end_date')) {
            $query->whereDate('meeting_date', '<=', $endDate);
        }

        $perPage = (int) $request->input('limit', 15);
        $perPage = max(1, min(50, $perPage));

        $events = $query
            ->orderByDesc('meeting_date')
            ->orderBy('meeting_time')
            ->paginate($perPage)
            ->appends($request->validated());

        return response()->json([
            'data' => AdkEventResource::collection($events),
            'meta' => [
                'currentPage' => $events->currentPage(),
                'perPage' => $events->perPage(),
                'total' => $events->total(),
                'lastPage' => $events->lastPage(),
            ],
        ]);
    }

    public function store(StoreAdkEventRequest $request): JsonResponse
    {
        $event = AdkEvent::create($request->validated());

        return response()->json([
            'message' => 'Event created successfully',
            'event' => new AdkEventResource($event),
        ], 201);
    }

    public function show(AdkEvent $event): AdkEventResource
    {
        return new AdkEventResource($event);
    }

    public function update(UpdateAdkEventRequest $request, AdkEvent $event): JsonResponse
    {
        $event->update($request->validated());

        return response()->json([
            'message' => 'Event updated successfully',
            'event' => new AdkEventResource($event),
        ]);
    }

    public function destroy(AdkEvent $event): JsonResponse
    {
        $event->delete();

        return response()->json([
            'message' => 'Event deleted successfully',
        ]);
    }
}
