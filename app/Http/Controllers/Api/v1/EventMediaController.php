<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventMedia\EventMediaBulkActionRequest;
use App\Http\Requests\EventMedia\EventMediaIndexRequest;
use App\Http\Requests\EventMedia\EventMediaReorderRequest;
use App\Http\Requests\EventMedia\EventMediaStoreRequest;
use App\Http\Requests\EventMedia\EventMediaUpdateRequest;
use App\Http\Requests\EventMedia\EventMediaUploadRequest;
use App\Http\Resources\EventMediaResource;
use App\Models\EventMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EventMediaController extends Controller
{
    public function index(EventMediaIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $query = EventMedia::query();

        if (!empty($validated['search'])) {
            $query->where(function (Builder $builder) use ($validated) {
                $search = $validated['search'];
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('caption', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('alt_text', 'like', "%{$search}%")
                    ->orWhere(function ($q) use ($search) {
                        $q->whereNotNull('meta')->whereJsonContains('meta->tags', $search);
                    });
            });
        }

        if (!empty($validated['media_type'])) {
            $query->where('media_type', $validated['media_type']);
        }

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', $validated['is_active']);
        }

        $sort = $validated['sort'] ?? 'manual';
        $query->when($sort === 'recent', fn ($q) => $q->orderByDesc('created_at'))
            ->when($sort === 'oldest', fn ($q) => $q->orderBy('created_at'))
            ->when($sort === 'title_asc', fn ($q) => $q->orderBy('title'))
            ->when($sort === 'title_desc', fn ($q) => $q->orderByDesc('title'))
            ->when($sort === 'manual', fn ($q) => $q->orderBy('sort_order')->orderByDesc('created_at'));

        $limit = $validated['limit'] ?? 12;
        $page = $validated['page'] ?? 1;

        $paginator = $query->paginate($limit, ['*'], 'page', $page);
        return response()->json([
            'data' => EventMediaResource::collection($paginator->items()),
            'meta' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pages' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(EventMediaStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['sort_order'] = $data['sort_order'] ?? $this->nextSortOrder();
        $media = EventMedia::create($data);

        return response()->json([
            'media' => EventMediaResource::make($media),
        ], 201);
    }

    public function upload(EventMediaUploadRequest $request): JsonResponse
    {
        $files = $request->file('files', []);
        $disk = Storage::disk('public');
        $uploads = [];

        foreach ($files as $file) {
            $mime = (string) $file->getMimeType();
            $isVideo = str_starts_with($mime, 'video/');
            $directory = $isVideo ? 'events/videos' : 'events/images';
            $path = $file->store($directory, 'public');

            $uploads[] = [
                'url' => url($disk->url($path)),
                'publicId' => $path,
                'mimeType' => $mime,
                'bytes' => $file->getSize(),
                'format' => $file->extension() ?? $file->getClientOriginalExtension(),
                'name' => $file->getClientOriginalName(),
                'mediaType' => $isVideo ? 'VIDEO' : 'IMAGE',
            ];
        }

        return response()->json([
            'files' => $uploads,
        ], 201);
    }

    public function show(EventMedia $eventMedia): JsonResponse
    {
        return response()->json([
            'media' => EventMediaResource::make($eventMedia),
        ]);
    }

    public function update(EventMediaUpdateRequest $request, EventMedia $eventMedia): JsonResponse
    {
        $eventMedia->update($request->validated());

        return response()->json([
            'media' => EventMediaResource::make($eventMedia),
        ]);
    }

    public function destroy(EventMedia $eventMedia): JsonResponse
    {
        $eventMedia->delete();

        return response()->json([
            'deleted' => true,
        ]);
    }

    public function bulkAction(EventMediaBulkActionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $ids = $data['ids'];
        $action = $data['action'];

        $query = EventMedia::query()->whereIn('id', $ids);
        $affected = 0;

        switch ($action) {
            case 'delete':
                $affected = $query->delete();
                break;
            case 'activate':
                $affected = $query->update(['is_active' => true]);
                break;
            case 'deactivate':
                $affected = $query->update(['is_active' => false]);
                break;
        }

        return response()->json([
            'action' => $action,
            'affected' => $affected,
        ]);
    }

    public function reorder(EventMediaReorderRequest $request): JsonResponse
    {
        $order = $request->validated()['order'];

        DB::transaction(function () use ($order) {
            foreach ($order as $item) {
                EventMedia::where('id', $item['id'])->update([
                    'sort_order' => $item['sort_order'],
                ]);
            }
        });

        return response()->json([
            'reordered' => count($order),
        ]);
    }

    private function nextSortOrder(): int
    {
        $max = EventMedia::max('sort_order');
        return is_null($max) ? 1 : $max + 1;
    }
}
