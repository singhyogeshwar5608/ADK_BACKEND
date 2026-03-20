<?php

namespace App\Http\Controllers\Api;

use App\Events\ProductBroadcastEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->buildProductResponse($request, Product::query());
    }

    public function publicIndex(Request $request): JsonResponse
    {
        $query = Product::query()->where('is_active', true)->orderByDesc('created_at');

        return $this->buildProductResponse($request, $query, enforceActive: true);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());
        event(new ProductBroadcastEvent($product->fresh()->toArray(), 'created'));

        return response()->json(['product' => $product], 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json(['product' => $product]);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());
        event(new ProductBroadcastEvent($product->fresh()->toArray(), 'updated'));

        return response()->json(['product' => $product]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $payload = $product->toArray();
        $product->delete();
        event(new ProductBroadcastEvent($payload, 'deleted'));

        return response()->json(['product' => $product]);
    }

    public function adjustStock(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'adjustment' => ['required', 'integer'],
        ]);

        $newStock = $product->stock + $validated['adjustment'];
        if ($newStock < 0) {
            return response()->json([
                'message' => 'Stock cannot be negative',
            ], 400);
        }

        $product->stock = $newStock;
        $product->save();
        event(new ProductBroadcastEvent($product->fresh()->toArray(), 'stock_adjusted'));

        return response()->json(['product' => $product]);
    }

    protected function buildProductResponse(Request $request, $query, bool $enforceActive = false): JsonResponse
    {
        $limit = (int) $request->query('limit', 25);
        $limit = max(1, min($limit, 100));

        $page = (int) $request->query('page', 1);
        $page = max(1, $page);

        if ($search = $request->query('search')) {
            $query->whereFullText(['name', 'description'], $search);
        }

        if ($category = $request->query('category')) {
            $query->whereJsonContains('categories', $category);
        }

        if (!$enforceActive && ($status = $request->query('status'))) {
            $query->where('is_active', $status === 'active');
        }

        if ($enforceActive) {
            $query->where('is_active', true);
        }

        $query->orderByDesc('created_at');

        $paginator = $query->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pages' => $paginator->lastPage(),
            ],
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }
}
