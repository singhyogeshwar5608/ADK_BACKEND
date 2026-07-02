<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\MemberProfileUploadRequest;
use App\Http\Requests\Media\ProductMediaUploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MediaController extends Controller
{
    public function uploadProducts(ProductMediaUploadRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->canAccessStaffPanelModules()) {
            throw ValidationException::withMessages([
                'authorization' => 'You do not have permission to upload product media.',
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
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $file = $request->file('file');

        // Unsigned preset upload (same as category logos / Flutter). Signed SDK upload triggers
        // "Invalid Signature" when CLOUDINARY_API_SECRET does not match the cloud or signing differs.
        try {
            $uploadPreset = trim((string) config('services.cloudinary.upload_preset', ''));
            $cloudName = trim((string) config('services.cloudinary.cloud_name', ''));

            if ($uploadPreset === '' || $cloudName === '') {
                throw ValidationException::withMessages([
                    'file' => 'Missing Cloudinary preset or cloud name. Set CLOUDINARY_CLOUD_NAME and CLOUDINARY_UPLOAD_PRESET in backend/.env (same values as Flutter), then run: php artisan config:clear',
                ]);
            }

            $uploadResult = $this->cloudinaryUnsignedImageUpload(
                $file,
                $cloudName,
                $uploadPreset,
                'members/profile',
            );

            $publicUrl = $uploadResult['secure_url'] ?? $uploadResult['url'] ?? null;
            $publicId = $uploadResult['public_id'] ?? '';
            $width = $uploadResult['width'] ?? null;
            $height = $uploadResult['height'] ?? null;
            $format = $uploadResult['format'] ?? null;
            $bytes = $file->getSize();

            if (! is_string($publicUrl) || $publicUrl === '') {
                throw new \RuntimeException('Cloudinary response missing image URL');
            }

            return response()->json([
                'file' => [
                    'url' => $publicUrl,
                    'secureUrl' => $publicUrl,
                    'publicId' => $publicId,
                    'bytes' => $bytes,
                    'width' => $width,
                    'height' => $height,
                    'format' => $format,
                    'name' => $file->getClientOriginalName(),
                ],
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Cloudinary upload failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);

            throw ValidationException::withMessages([
                'file' => 'Failed to upload image to Cloudinary: ' . $e->getMessage(),
            ]);
        }
    }

    public function uploadMemberQrCode(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $file = $request->file('file');
        
        if (!$file) {
            throw ValidationException::withMessages([
                'file' => 'No file was provided.',
            ]);
        }

        try {
            $uploadPreset = trim((string) config('services.cloudinary.upload_preset', ''));
            $cloudName = trim((string) config('services.cloudinary.cloud_name', ''));

            if ($uploadPreset === '' || $cloudName === '') {
                throw ValidationException::withMessages([
                    'file' => 'Missing Cloudinary preset or cloud name.',
                ]);
            }

            $uploadResult = $this->cloudinaryUnsignedImageUpload(
                $file,
                $cloudName,
                $uploadPreset,
                'members/qr_codes',
            );

            $publicUrl = $uploadResult['secure_url'] ?? $uploadResult['url'] ?? null;

            return response()->json([
                'file' => [
                    'url' => $publicUrl,
                    'secureUrl' => $publicUrl,
                    'name' => $file->getClientOriginalName(),
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('QR Code upload failed', [
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'file' => 'Failed to upload QR Code: ' . $e->getMessage(),
            ]);
        }
    }

    public function uploadCategoryLogo(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->canAccessStaffPanelModules()) {
            throw ValidationException::withMessages([
                'authorization' => 'You do not have permission to upload category logos.',
            ]);
        }

        $file = $request->file('file');
        
        if (!$file) {
            throw ValidationException::withMessages([
                'file' => 'No file was provided.',
            ]);
        }

        // Validate file type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw ValidationException::withMessages([
                'file' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.',
            ]);
        }

        // Validate file size (max 2MB)
        if ($file->getSize() > 2 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'file' => 'File size must be less than 2MB.',
            ]);
        }

        // Upload to Cloudinary (same as Flutter: unsigned preset only — never use SDK signed upload here or
        // wrong/missing API_SECRET triggers "api_secret mismatch" even when the app works from mobile.)
        try {
            $uploadPreset = trim((string) config('services.cloudinary.upload_preset', ''));
            $cloudName = trim((string) config('services.cloudinary.cloud_name', ''));

            if ($uploadPreset === '' || $cloudName === '') {
                throw ValidationException::withMessages([
                    'file' => 'Missing Cloudinary preset or cloud name. Set CLOUDINARY_CLOUD_NAME and CLOUDINARY_UPLOAD_PRESET in backend/.env (same values as Flutter), then run: php artisan config:clear',
                ]);
            }

            $uploadResult = $this->cloudinaryUnsignedImageUpload(
                $file,
                $cloudName,
                $uploadPreset,
            );

            $publicUrl = $uploadResult['secure_url'] ?? $uploadResult['url'] ?? null;
            $publicId = $uploadResult['public_id'] ?? '';
            $width = $uploadResult['width'] ?? null;
            $height = $uploadResult['height'] ?? null;
            $format = $uploadResult['format'] ?? null;
            $bytes = $file->getSize();

            if (! is_string($publicUrl) || $publicUrl === '') {
                throw new \RuntimeException('Cloudinary response missing image URL');
            }

            return response()->json([
                'file' => [
                    'url' => $publicUrl,
                    'secureUrl' => $publicUrl,
                    'publicId' => $publicId,
                    'bytes' => $bytes,
                    'width' => $width,
                    'height' => $height,
                    'format' => $format,
                    'name' => $file->getClientOriginalName(),
                ],
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Cloudinary category logo upload failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);

            throw ValidationException::withMessages([
                'file' => 'Failed to upload category logo to Cloudinary: ' . $e->getMessage(),
            ]);
        }
    }

    public function uploadHeroSlider(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->canAccessStaffPanelModules()) {
            throw ValidationException::withMessages([
                'authorization' => 'You do not have permission to upload hero slider images.',
            ]);
        }

        $request->validate([
            'file' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');

        // Unsigned preset upload (same as category logos / members / Flutter). Signed SDK upload fails with
        // "Invalid Signature" when CLOUDINARY_API_SECRET does not match the cloud or signing differs.
        try {
            $uploadPreset = trim((string) config('services.cloudinary.upload_preset', ''));
            $cloudName = trim((string) config('services.cloudinary.cloud_name', ''));

            if ($uploadPreset === '' || $cloudName === '') {
                throw ValidationException::withMessages([
                    'file' => 'Missing Cloudinary preset or cloud name. Set CLOUDINARY_CLOUD_NAME and CLOUDINARY_UPLOAD_PRESET in backend/.env (same values as Flutter), then run: php artisan config:clear',
                ]);
            }

            $uploadResult = $this->cloudinaryUnsignedImageUpload(
                $file,
                $cloudName,
                $uploadPreset,
                'hero-slider',
            );

            $publicUrl = $uploadResult['secure_url'] ?? $uploadResult['url'] ?? null;
            $publicId = $uploadResult['public_id'] ?? '';
            $width = $uploadResult['width'] ?? null;
            $height = $uploadResult['height'] ?? null;
            $format = $uploadResult['format'] ?? null;
            $bytes = $file->getSize();

            if (! is_string($publicUrl) || $publicUrl === '') {
                throw new \RuntimeException('Cloudinary response missing image URL');
            }

            return response()->json([
                'url' => $publicUrl,
                'secureUrl' => $publicUrl,
                'publicId' => $publicId,
                'width' => $width,
                'height' => $height,
                'format' => $format,
                'size' => $bytes,
                'name' => $file->getClientOriginalName(),
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Cloudinary hero slider upload failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);

            throw ValidationException::withMessages([
                'file' => 'Failed to upload hero slider image: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Same contract as Flutter unsigned upload: multipart file + upload_preset only (optional folder).
     * Uses Guzzle directly so no Basic auth / API secret is sent — avoids "api_secret mismatch" from Cloudinary.
     *
     * @param  string|null  $folderOverride  If set, used as Cloudinary `folder` (e.g. members/profile). If null, uses category_logo_folder from config.
     * @return array<string, mixed>
     */
    private function cloudinaryUnsignedImageUpload(
        \Illuminate\Http\UploadedFile $file,
        string $cloudName,
        string $uploadPreset,
        ?string $folderOverride = null,
    ): array {
        $endpoint = sprintf(
            'https://api.cloudinary.com/v1_1/%s/image/upload',
            rawurlencode($cloudName)
        );

        $path = $file->getRealPath() ?: $file->getPathname();
        $stream = fopen($path, 'r');
        if ($stream === false) {
            throw new \RuntimeException('Could not read upload file.');
        }

        $multipart = [
            ['name' => 'upload_preset', 'contents' => $uploadPreset],
        ];

        $folder = $folderOverride !== null
            ? trim($folderOverride)
            : trim((string) config('services.cloudinary.category_logo_folder', ''));
        if ($folder !== '') {
            $multipart[] = ['name' => 'folder', 'contents' => $folder];
        }

        $multipart[] = [
            'name' => 'file',
            'contents' => $stream,
            'filename' => $file->getClientOriginalName(),
        ];

        $client = new Client([
            'timeout' => 120,
            'http_errors' => false,
        ]);

        try {
            $response = $client->post($endpoint, ['multipart' => $multipart]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $status = $response->getStatusCode();
        $json = json_decode((string) $response->getBody(), true);
        if (! is_array($json)) {
            $json = [];
        }

        if ($status < 200 || $status >= 300) {
            $err = $json['error']['message'] ?? $json['error'] ?? (string) $response->getBody();
            throw new \RuntimeException(is_string($err) ? $err : 'Cloudinary upload failed');
        }

        return $json;
    }
}
