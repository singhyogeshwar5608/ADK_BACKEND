<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\OrderIndexRequest;
use App\Http\Requests\Order\OrderRefundRequest;
use App\Http\Requests\Order\OrderStatusUpdateRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Services\BvService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(private readonly BvService $bvService)
    {
    }

    public function index(OrderIndexRequest $request): JsonResponse
    {
        $query = Order::query();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($paymentStatus = $request->string('payment_status')->toString()) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($memberSearch = $request->string('member_search')->toString()) {
            $query->where(function (Builder $builder) use ($memberSearch) {
                $builder->where('member_snapshot->memberId', 'like', "%{$memberSearch}%")
                    ->orWhere('member_snapshot->fullName', 'like', "%{$memberSearch}%")
                    ->orWhere('member_snapshot->email', 'like', "%{$memberSearch}%");
            });
        }

        $limit = (int) $request->input('limit', 10);
        $page = (int) $request->input('page', 1);

        $paginator = $query
            ->orderByDesc('created_at')
            ->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'data' => OrderResource::collection($paginator->items()),
            'meta' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pages' => $paginator->lastPage(),
            ],
        ]);
    }

    public function updateStatus(OrderStatusUpdateRequest $request, Order $order): JsonResponse
    {
        $data = $request->validated();
        $actor = auth()->user();
        $actorName = $actor?->full_name ?? $actor?->email ?? 'System';

        if ($order->status === 'CANCELLED' && $data['status'] !== 'CANCELLED') {
            throw ValidationException::withMessages([
                'status' => 'Cannot update a cancelled order.',
            ]);
        }

        DB::transaction(function () use ($order, $data, $actorName) {
            $order->status = $data['status'];
            $history = $order->history ?? [];
            $history[] = [
                'status' => $data['status'],
                'note' => $data['note'] ?? null,
                'changedBy' => $actorName,
                'changedAt' => now()->toIso8601String(),
            ];
            $order->history = $history;

            if ($data['status'] === 'CANCELLED' && $order->payment_status === 'PAID') {
                $order->payment_status = 'REFUNDED';
                $this->restoreInventory($order);
            }

            $order->save();
        });

        $this->attemptBvAward($order->refresh());

        return response()->json([
            'order' => OrderResource::make($order->refresh()),
        ]);
    }

    public function refund(OrderRefundRequest $request, Order $order): JsonResponse
    {
        if ($order->payment_status !== 'PAID') {
            throw ValidationException::withMessages([
                'order' => 'Only paid orders can be refunded.',
            ]);
        }

        $data = $request->validated();
        $actor = auth()->user();
        $actorName = $actor?->full_name ?? $actor?->email ?? 'System';

        DB::transaction(function () use ($order, $data, $actorName) {
            $order->payment_status = 'REFUNDED';
            $order->status = 'CANCELLED';

            $history = $order->history ?? [];
            $history[] = [
                'status' => 'CANCELLED',
                'note' => $data['note'] ?? 'Refund processed',
                'changedBy' => $actorName,
                'changedAt' => now()->toIso8601String(),
            ];
            $order->history = $history;

            $this->restoreInventory($order);
            $order->save();
        });

        return response()->json([
            'order' => OrderResource::make($order->refresh()),
        ]);
    }

    private function restoreInventory(Order $order): void
    {
        $items = $order->items ?? [];
        foreach ($items as $item) {
            $productId = $item['productId'] ?? $item['product_id'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 0);

            if (!$productId || $quantity <= 0) {
                continue;
            }

            try {
                Product::query()
                    ->where('id', $productId)
                    ->increment('stock', $quantity);
            } catch (\Throwable $exception) {
                Log::warning('Failed to restore inventory for product', [
                    'product_id' => $productId,
                    'order_id' => $order->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function attemptBvAward(Order $order): void
    {
        if (
            $order->status === 'DELIVERED'
            && $order->payment_status === 'PAID'
            && $order->bv_awarded_at === null
            && $order->total_bv > 0
        ) {
            $this->bvService->awardForOrder($order);
        }
    }
}
