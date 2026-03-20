<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\MemberProfileUploadRequest;
use App\Http\Requests\Media\ProductMediaUploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MediaController extends Controller
{
    public function uploadProducts(ProductMediaUploadRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->role !== 'ADMIN') {
            throw ValidationException::withMessages([
                'authorization' => 'Only admins can upload product media.',
            ]);
        }

        $files = Arr::wrap($request->file('files'));
        Log::info('Product media upload attempt', [
            'user_id' => $user?->id,
            'files_count' => count($files),
            'file_keys' => array_keys($request->allFiles()),
        ]);
        if (empty($files)) {
            throw ValidationException::withMessages([
                'files' => 'No files were provided.',
            ]);
        }

        $disk = Storage::disk('public');
        $uploads = [];

        foreach ($files as $file) {
            $path = $file->store('products', 'public');
            $absolutePath = $disk->path($path);
            $imageSize = @getimagesize($absolutePath);

            $publicUrl = url($disk->url($path));

            $uploads[] = [
                'url' => $publicUrl,
                'secureUrl' => $publicUrl,
                'publicId' => $path,
                'bytes' => $file->getSize(),
                'width' => $imageSize[0] ?? null,
                'height' => $imageSize[1] ?? null,
                'format' => $file->extension() ?? $file->getClientOriginalExtension(),
                'name' => $file->getClientOriginalName(),
            ];
        }

        return response()->json([
            'files' => $uploads,
        ], 201);
    }

    public function uploadMemberProfile(MemberProfileUploadRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->role !== 'ADMIN') {
            throw ValidationException::withMessages([
                'authorization' => 'Only admins can upload member profile images.',
            ]);
        }

        $file = $request->file('file');
        $path = $file->store('members/profile', 'public');
        $disk = Storage::disk('public');
        $absolutePath = $disk->path($path);
        $imageSize = @getimagesize($absolutePath);
        $publicUrl = url($disk->url($path));

        return response()->json([
            'file' => [
                'url' => $publicUrl,
                'secureUrl' => $publicUrl,
                'publicId' => $path,
                'bytes' => $file->getSize(),
                'width' => $imageSize[0] ?? null,
                'height' => $imageSize[1] ?? null,
                'format' => $file->extension() ?? $file->getClientOriginalExtension(),
                'name' => $file->getClientOriginalName(),
            ],
        ], 201);
    }
}
