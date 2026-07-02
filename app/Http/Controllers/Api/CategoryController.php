<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Category::query();

        if ($search = $request->query('search')) {
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%"));
        }

        if (($status = $request->query('status')) !== null) {
            $query->where('is_active', filter_var($status, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE));
        }

        $perPage = (int) $request->query('limit', 25);
        $perPage = max(1, min($perPage, 100));

        $categories = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $categories->items(),
            'meta' => [
                'page' => $categories->currentPage(),
                'limit' => $categories->perPage(),
                'total' => $categories->total(),
                'pages' => $categories->lastPage(),
            ],
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return response()->json(['category' => $category], 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json(['category' => $category]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json(['category' => $category]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['category' => $category]);
    }

    public function updateLogo(Request $request, Category $category): JsonResponse
    {
        $request->validate([
            'logo_url' => ['required', 'string', 'url'],
        ]);

        $category->update([
            'logo_url' => $request->logo_url,
        ]);

        return response()->json(['category' => $category]);
    }

    public function publicIndex(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 100);
        $limit = max(1, min($limit, 100));

        $query = Category::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($search = $request->query('search')) {
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%"));
        }

        $categories = $query->limit($limit)->get();

        return response()->json([
            'data' => $categories,
            'meta' => [
                'count' => $categories->count(),
                'limit' => $limit,
            ],
        ]);
    }
}
