<?php

use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\EventMediaController;
use App\Http\Controllers\Api\v1\MemberController;
use App\Http\Controllers\Api\v1\MediaController;
use App\Http\Controllers\Api\v1\MediaProxyController;
use App\Http\Controllers\Api\v1\AdkEventController;
use App\Http\Controllers\Api\v1\CataloguePageController;
use App\Http\Controllers\Api\v1\OrderController;
use App\Http\Controllers\Api\v1\ReportController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });

    Route::get('products/public', [ProductController::class, 'publicIndex'])->name('products.public');
    Route::get('event-media', [EventMediaController::class, 'index'])->name('event-media.public-index');
    Route::get('catalogue', [CataloguePageController::class, 'index'])->name('catalogue.public-index');
    Route::get('admin/events', [AdkEventController::class, 'index'])->name('events.public-index');
    Route::get('admin/events/{event}', [AdkEventController::class, 'show'])->name('events.public-show');
    Route::match(['GET', 'OPTIONS'], 'media/{path}', MediaProxyController::class)
        ->where('path', '.*')
        ->name('media-proxy');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', fn (Request $request) => $request->user())->name('user');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::apiResource('products', ProductController::class);
        Route::post('products/{product}/stock', [ProductController::class, 'adjustStock'])
            ->name('products.adjust-stock');

        Route::apiResource('categories', CategoryController::class);

        Route::get('members', [MemberController::class, 'index'])->name('members.index');
        Route::post('members', [MemberController::class, 'store'])->name('members.store');
        Route::get('members/{member}', [MemberController::class, 'show'])->name('members.show');
        Route::patch('members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');
        Route::get('members/{member}/tree', [MemberController::class, 'tree'])->name('members.tree');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
        Route::post('orders/{order}/refund', [OrderController::class, 'refund'])->name('orders.refund');

        Route::get('reports/dashboard', [ReportController::class, 'dashboard'])->name('reports.dashboard');

        Route::post('media/products', [MediaController::class, 'uploadProducts'])->name('media.products.upload');
        Route::post('media/members/profile', [MediaController::class, 'uploadMemberProfile'])->name('media.members.profile');

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::post('events', [AdkEventController::class, 'store'])->name('events.store');
            Route::put('events/{event}', [AdkEventController::class, 'update'])->name('events.update');
            Route::delete('events/{event}', [AdkEventController::class, 'destroy'])->name('events.destroy');
        });

        Route::prefix('catalogue')->name('catalogue.')->group(function () {
            Route::post('/', [CataloguePageController::class, 'store'])->name('store');
            Route::put('{catalogue}', [CataloguePageController::class, 'update'])->name('update');
            Route::delete('{catalogue}', [CataloguePageController::class, 'destroy'])->name('destroy');
            Route::patch('reorder', [CataloguePageController::class, 'reorder'])->name('reorder');
        });

        Route::prefix('event-media')->name('event-media.')->group(function () {
            Route::post('/', [EventMediaController::class, 'store'])->name('store');
            Route::post('/upload', [EventMediaController::class, 'upload'])->name('upload');
            Route::post('/bulk', [EventMediaController::class, 'bulkAction'])->name('bulk');
            Route::post('/reorder', [EventMediaController::class, 'reorder'])->name('reorder');
            Route::get('/{eventMedia}', [EventMediaController::class, 'show'])->name('show');
            Route::patch('/{eventMedia}', [EventMediaController::class, 'update'])->name('update');
            Route::delete('/{eventMedia}', [EventMediaController::class, 'destroy'])->name('destroy');
        });
    });
});
