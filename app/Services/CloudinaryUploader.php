<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Exception\ApiErrorException;
use Cloudinary\Exception\ConfigurationException;
use GuzzleHttp\Client;
use Illuminate\Http\UploadedFile;

class CloudinaryUploader
{
    private Cloudinary $client;
    private ?string $folder;

    /**
     * @throws ConfigurationException
     */
    public function __construct()
    {
        $config = config('services.cloudinary');

        $this->client = new Cloudinary([
            'cloud' => [
                'cloud_name' => $config['cloud_name'],
                'api_key' => $config['api_key'],
                'api_secret' => $config['api_secret'],
            ],
        ]);

        $this->folder = $config['folder'] ?: null;
    }

    /**
     * @return array{url:string, public_id:string}
     */
    public function upload(UploadedFile $file): array
    {
        $config = config('services.cloudinary');
        $cloudName = trim((string) ($config['cloud_name'] ?? ''));
        $preset = trim((string) ($config['upload_preset'] ?? ''));

        // Prefer unsigned preset upload (same as Flutter / category logos). No API signature —
        // avoids "Invalid Signature" when server api_secret does not match or signing params differ.
        if ($cloudName !== '' && $preset !== '') {
            return $this->uploadViaUnsignedPreset($file, $cloudName, $preset);
        }

        $path = $file->getRealPath() ?: $file->getPathname();
        $options = [
            'resource_type' => 'image',
            'use_filename' => true,
            'unique_filename' => true,
        ];
        if ($this->folder) {
            $options['folder'] = $this->folder;
        }

        $result = $this->client->uploadApi()->upload($path, $options);

        return [
            'url' => $result['secure_url'] ?? $result['url'],
            'public_id' => $result['public_id'],
        ];
    }

    /**
     * Multipart POST to Cloudinary with upload_preset only (no signature).
     * Preset in Cloudinary console must allow the folder (if CLOUDINARY_FOLDER is set).
     *
     * @return array{url: string, public_id: string}
     */
    private function uploadViaUnsignedPreset(UploadedFile $file, string $cloudName, string $preset): array
    {
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
            ['name' => 'upload_preset', 'contents' => $preset],
        ];

        if ($this->folder) {
            $multipart[] = ['name' => 'folder', 'contents' => $this->folder];
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

        $url = $json['secure_url'] ?? $json['url'] ?? '';
        $publicId = $json['public_id'] ?? '';
        if ($url === '' || $publicId === '') {
            throw new \RuntimeException('Cloudinary response missing url or public_id');
        }

        return [
            'url' => $url,
            'public_id' => $publicId,
        ];
    }

    public function delete(?string $publicId): void
    {
        if (!$publicId) {
            return;
        }

        try {
            $this->client->uploadApi()->destroy($publicId, ['invalidate' => true]);
        } catch (ApiErrorException) {
            // Ignore delete errors so they don't block user actions.
        }
    }
}
