<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WishlistItemResource;
use App\Models\Member;
use App\Models\Product;
use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WishlistController extends Controller
{
    public function showShared(string $token): JsonResponse
    {
        // Token format: {partnerId}-{timestamp}
        $lastDash = strrpos($token, '-');
        if ($lastDash === false) {
            $partnerId = $token;
        } else {
            $partnerId = substr($token, 0, $lastDash);
        }

        try {
            $member = Member::where('member_id', $partnerId)->firstOrFail();
            
            $items = $member
                ->wishlistItems()
                ->whereHas('product', fn ($q) => $q->where('is_active', true))
                ->with('product')
                ->latest()
                ->get();

            return response()->json([
                'data' => WishlistItemResource::collection($items),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Shared wishlist not found.',
                'data' => [],
            ], 404);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $member = $request->user();

        $items = $member
            ->wishlistItems()
            ->whereHas('product', fn ($q) => $q->where('is_active', true))
            ->with('product')
            ->latest()
            ->get();

        return response()->json([
            'data' => WishlistItemResource::collection($items),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'productId' => ['required', 'integer', 'exists:products,id'],
        ]);

        $member = $request->user();
        $productId = $validated['productId'];

        $product = Product::findOrFail($productId);
        if (! $product->is_active) {
            throw ValidationException::withMessages([
                'productId' => 'This product is not available.',
            ]);
        }

        $wishlistItem = WishlistItem::firstOrCreate([
            'member_id' => $member->id,
            'product_id' => $productId,
        ]);

        $wishlistItem->loadMissing('product');

        return response()->json([
            'item' => WishlistItemResource::make($wishlistItem),
        ], 201);
    }

    public function destroy(Request $request, int $productId): JsonResponse
    {
        $member = $request->user();

        $deleted = WishlistItem::where('member_id', $member->id)
            ->where('product_id', $productId)
            ->delete();

        if (! $deleted) {
            throw ValidationException::withMessages([
                'productId' => 'Item not found in wishlist.',
            ]);
        }

        return response()->json([
            'deleted' => true,
        ]);
    }
}
