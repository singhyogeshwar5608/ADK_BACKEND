<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CataloguePage\CataloguePageIndexRequest;
use App\Http\Requests\CataloguePage\ReorderCataloguePageRequest;
use App\Http\Requests\CataloguePage\StoreCataloguePageRequest;
use App\Http\Requests\CataloguePage\UpdateCataloguePageRequest;
use App\Http\Resources\CataloguePageResource;
use App\Models\CataloguePage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CataloguePageController extends Controller
{
    public function index(CataloguePageIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $query = CataloguePage::query();

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where('title', 'like', "%{$search}%");
        }

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', $validated['is_active']);
        }

        $limit = $validated['limit'] ?? 20;
        $page = $validated['page'] ?? 1;

        $query->orderBy('order_index')->orderByDesc('created_at');
        $paginator = $query->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'data' => CataloguePageResource::collection($paginator->items()),
            'meta' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pages' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(StoreCataloguePageRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['image_path'] = $this->storeImage($request->file('image'));
        $data['order_index'] = $this->nextOrderIndex();

        $page = CataloguePage::create($data);

        return response()->json([
            'page' => CataloguePageResource::make($page),
        ], 201);
    }

    public function update(UpdateCataloguePageRequest $request, CataloguePage $catalogue): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($catalogue->image_path && Storage::disk('public')->exists($catalogue->image_path)) {
                Storage::disk('public')->delete($catalogue->image_path);
            }
            $data['image_path'] = $this->storeImage($request->file('image'));
        }

        $catalogue->update($data);

        return response()->json([
            'page' => CataloguePageResource::make($catalogue),
        ]);
    }

    public function destroy(CataloguePage $catalogue): JsonResponse
    {
        if ($catalogue->image_path && Storage::disk('public')->exists($catalogue->image_path)) {
            Storage::disk('public')->delete($catalogue->image_path);
        }

        $catalogue->delete();

        return response()->json([
            'deleted' => true,
        ]);
    }

    public function reorder(ReorderCataloguePageRequest $request): JsonResponse
    {
        $order = $request->validated()['order'];

        DB::transaction(function () use ($order) {
            foreach ($order as $item) {
                CataloguePage::whereKey($item['id'])->update(['order_index' => $item['order_index']]);
            }
        });

        return response()->json([
            'reordered' => count($order),
        ]);
    }

    private function storeImage(UploadedFile $file): string
    {
        return $file->store('catalogue', 'public');
    }

    private function nextOrderIndex(): int
    {
        $max = CataloguePage::max('order_index');
        return is_null($max) ? 1 : $max + 1;
    }
}
