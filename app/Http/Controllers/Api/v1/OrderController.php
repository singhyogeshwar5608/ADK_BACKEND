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
use App\Services\ShiprocketService;
use App\Services\WhatsAppService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        private readonly BvService $bvService,
        private readonly ShiprocketService $shiprocketService,
        private readonly WhatsAppService $whatsAppService
    ) {
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
        $oldStatus = $order->status;

        if ($oldStatus === 'CANCELLED' && $data['status'] !== 'CANCELLED') {
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

        $refreshedOrder = $order->refresh();

        $this->attemptBvAward($refreshedOrder);

        $this->whatsAppService->sendOrderStatusNotification(
            $refreshedOrder,
            $oldStatus,
            $data['status']
        );

        $whatsAppLink = $this->whatsAppService->getOrderStatusWhatsAppLink(
            $refreshedOrder,
            $oldStatus,
            $data['status']
        );

        return response()->json([
            'order' => OrderResource::make($refreshedOrder),
            'whatsapp_link' => $whatsAppLink,
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

    /**
     * Create a mock order for testing purposes
     */
    public function storeMock(): JsonResponse
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $request = request();
            
            $validated = $request->validate([
                'total_amount' => 'required|numeric|min:0',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0',
                'items.*.bv' => 'required|integer|min:0',
                'shipping_details' => 'nullable|array',
                'shipping_details.full_name' => 'required_with:shipping_details|string',
                'shipping_details.phone' => 'required_with:shipping_details|string',
                'shipping_details.state' => 'required_with:shipping_details|string',
                'shipping_details.city' => 'required_with:shipping_details|string',
                'shipping_details.zip_code' => 'required_with:shipping_details|string',
                'shipping_details.address' => 'required_with:shipping_details|string',
            ]);

            $order = DB::transaction(function () use ($validated, $user) {
                $subtotal = 0;
                $totalBv = 0;
                $items = [];

                foreach ($validated['items'] as $item) {
                    $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                    if ($product->stock < $item['quantity']) {
                        throw new \Exception("Insufficient stock for product: {$product->name}");
                    }

                    $product->decrement('stock', $item['quantity']);

                    $itemTotal = $item['price'] * $item['quantity'];
                    $itemBv = $item['bv'] * $item['quantity'];
                    
                    $subtotal += $itemTotal;
                    $totalBv += $itemBv;
                    
                    $items[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'bv' => $item['bv'],
                        'total' => $itemTotal,
                        'total_bv' => $itemBv,
                        'hsn_code' => $product->hsn_code,
                    ];
                }

                return Order::create([
                    'member_id' => $user->id,
                    'member_snapshot' => [
                        'memberId' => $user->member_id ?? $user->id,
                        'fullName' => $user->full_name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                    ],
                    'items' => $items,
                    'subtotal' => $validated['total_amount'],
                    'discount' => 0,
                    'total' => $validated['total_amount'],
                    'total_bv' => $totalBv,
                    'status' => 'PENDING',
                    'payment_method' => 'mock',
                    'payment_status' => 'PAID',
                    'shipping_address' => $validated['shipping_details'] ?? null,
                    'history' => [
                        [
                            'status' => 'PENDING',
                            'payment_status' => 'PENDING',
                            'timestamp' => now()->toISOString(),
                            'actor' => 'system',
                            'note' => 'Mock order created for testing'
                        ],
                        [
                            'status' => 'PENDING',
                            'payment_status' => 'PAID',
                            'timestamp' => now()->toISOString(),
                            'actor' => 'system',
                            'note' => 'Mock order payment confirmed'
                        ],
                    ],
                ]);
            });
            
            $this->bvService->awardForOrder($order);
            $this->syncWithShiprocket($order);

            $refreshedOrder = $order->refresh();
            $this->whatsAppService->sendOrderNotification($refreshedOrder);
            $whatsAppLink = $this->whatsAppService->getOrderWhatsAppLink($refreshedOrder);

            return response()->json([
                'success' => true,
                'message' => 'Mock order created successfully',
                'order' => new OrderResource($refreshedOrder),
                'awb_code' => $refreshedOrder->awb_code,
                'tracking_url' => $refreshedOrder->tracking_url,
                'shipping_status' => $refreshedOrder->shipping_status,
                'whatsapp_link' => $whatsAppLink,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Mock order creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guest checkout - creates order without member
     */
    public function storeGuestCheckout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'shipping_address' => 'required|array',
        ]);

        try {
            $order = DB::transaction(function () use ($validated) {
                $items = array_map(function ($item) {
                    $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                    if ($product->stock < $item['quantity']) {
                        throw new \Exception("Insufficient stock for product: {$product->name}");
                    }

                    $product->decrement('stock', $item['quantity']);

                    return [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'bv' => $product->bv,
                        'total' => $item['price'] * $item['quantity'],
                        'hsn_code' => $product->hsn_code,
                    ];
                }, $validated['items']);

                return Order::create([
                    'member_id' => null,
                    'member_snapshot' => [],
                    'items' => $items,
                    'subtotal' => $validated['total_amount'],
                    'discount' => 0,
                    'total' => $validated['total_amount'],
                    'total_bv' => 0,
                    'status' => 'PENDING',
                    'payment_method' => 'guest',
                    'payment_status' => 'PENDING',
                    'shipping_address' => $validated['shipping_address'],
                ]);
            });

            $this->syncWithShiprocket($order);

            return response()->json([
                'success' => true,
                'order' => new OrderResource($order),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Sync order with Shiprocket for shipping
     */
    private function syncWithShiprocket(Order $order): void
    {
        try {
            $this->shiprocketService->createOrder($order);
        } catch (\Exception $e) {
            Log::error('Shiprocket sync failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
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
