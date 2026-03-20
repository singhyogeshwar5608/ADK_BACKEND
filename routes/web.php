<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/storage-proxy/{path}', function (string $path) {
    $cleanPath = ltrim($path, '/');

    if (str_starts_with($cleanPath, 'storage/')) {
        $cleanPath = ltrim(substr($cleanPath, 8), '/');
    }

    $disk = Storage::disk('public');

    if (!$disk->exists($cleanPath)) {
        abort(404);
    }

    return $disk->response($cleanPath, null, [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => 'Origin, Content-Type, Accept, Authorization, X-Requested-With',
        'Access-Control-Expose-Headers' => 'Content-Type, Content-Length',
    ]);
})->where('path', '.*')->name('storage.proxy');

Route::get('/', function () {
    return view('welcome');
});
