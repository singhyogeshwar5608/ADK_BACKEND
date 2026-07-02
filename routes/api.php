<?php

use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\EventMediaController;
use App\Http\Controllers\Api\v1\HeroSliderController;
use App\Http\Controllers\Api\v1\MemberController;
use App\Http\Controllers\Api\v1\MediaController;
use App\Http\Controllers\Api\v1\MediaProxyController;
use App\Http\Controllers\Api\v1\AdkEventController;
use App\Http\Controllers\Api\v1\CataloguePageController;
use App\Http\Controllers\Api\v1\OrderController;
use App\Http\Controllers\Api\v1\PaymentController;
use App\Http\Controllers\Api\v1\PurchaseController;
use App\Http\Controllers\Api\v1\ReportController;
use App\Http\Controllers\Api\v1\SettingsController;
use App\Http\Controllers\Api\v1\SocialLinkController;
use App\Http\Controllers\Api\v1\WishlistController;
use App\Http\Controllers\Api\v1\ShippingController;
use App\Http\Controllers\Api\v1\ShiprocketWebhookController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\v1\DeliveryCenterController;
use App\Http\Controllers\Api\v1\ReferralController;
use App\Http\Controllers\Api\v1\PincodeController;
use App\Http\Controllers\Api\KycController;
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
    Route::get('categories/public', [CategoryController::class, 'publicIndex'])->name('categories.public');
    Route::get('catalogue', [CataloguePageController::class, 'index'])->name('catalogue.public-index');
    Route::get('admin/events', [AdkEventController::class, 'index'])->name('events.public-index');
    Route::get('admin/events/{event}', [AdkEventController::class, 'show'])->name('events.public-show');
    Route::get('members/public', [MemberController::class, 'publicList'])->name('members.public');
    Route::match(['GET', 'OPTIONS'], 'pincode/{pincode}', [PincodeController::class, 'show'])->name('pincode.show');
    Route::match(['GET', 'OPTIONS'], 'media/{path}', MediaProxyController::class)
        ->where('path', '.*')
        ->name('media-proxy');

    
    // Payment routes
    Route::get('payment/razorpay-key', [PaymentController::class, 'getKeyId'])->name('payment.razorpay-key');
    Route::post('payment/create-order', [PaymentController::class, 'createOrder'])->name('payment.create-order');
    Route::post('payment/verify', [PaymentController::class, 'verifyPayment'])->name('payment.verify');

    // Public delivery centers route (accessible to everyone)
    Route::apiResource('delivery-centers', DeliveryCenterController::class);
    
    // Public hero slider routes (accessible to everyone)
    Route::get('hero-sliders/active', [HeroSliderController::class, 'active'])->name('hero-sliders.active');

    // Guest checkout (no member login; email captured in shipping_details)
    Route::post('orders/guest-checkout', [OrderController::class, 'storeGuestCheckout'])
        ->middleware('throttle:20,1')
        ->name('orders.guest-checkout');

    Route::get('wishlist/shared/{token}', [WishlistController::class, 'showShared'])->name('wishlist.shared');
    Route::get('social-links', [SocialLinkController::class, 'index'])->name('social-links.index');

    // Shiprocket Webhook (Restricted keyword "shiprocket" removed to avoid Dashboard error)
    Route::post('logistic/callback', [ShiprocketWebhookController::class, 'handle'])->name('shiprocket.webhook');
    Route::get('logistic/callback', function() {
        return response()->json(['success' => true, 'message' => 'Webhook endpoint is reachable']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        // Shipping routes
        Route::prefix('shipping')->name('shipping.')->group(function () {
            Route::post('check-serviceability', [ShippingController::class, 'checkServiceability'])->name('serviceability');
            Route::post('create-order', [ShippingController::class, 'createOrder'])->name('create-order');
            Route::post('generate-awb', [ShippingController::class, 'generateAwb'])->name('generate-awb');
            Route::post('request-pickup', [ShippingController::class, 'requestPickup'])->name('request-pickup');
        });
        
        Route::get('orders/{id}/tracking', [ShippingController::class, 'getTracking'])->name('orders.tracking');

        // Settings routes
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/user', fn (Request $request) => $request->user())->name('user');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::get('auth/team-stats', [AuthController::class, 'teamStats'])->name('auth.team-stats');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        
        Route::apiResource('products', ProductController::class);
        Route::post('products/{product}/stock', [ProductController::class, 'adjustStock'])
            ->name('products.adjust-stock');

        Route::apiResource('categories', CategoryController::class);
        Route::patch('categories/{category}/logo', [CategoryController::class, 'updateLogo'])->name('categories.update-logo');

        Route::get('members', [MemberController::class, 'index'])->name('members.index');
        Route::post('members', [MemberController::class, 'store'])->name('members.store');
        Route::get('members/team', [MemberController::class, 'team'])->name('members.team');
        Route::get('members/team/test', [MemberController::class, 'teamTest'])->name('members.team.test');
        Route::get('members/{member}', [MemberController::class, 'show'])->name('members.show');
        Route::patch('members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');
        Route::get('members/{member}/tree', [MemberController::class, 'tree'])->name('members.tree');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('orders/mock', [OrderController::class, 'storeMock'])->name('orders.mock');
        Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
        Route::post('orders/{order}/refund', [OrderController::class, 'refund'])->name('orders.refund');

        Route::get('reports/dashboard', [ReportController::class, 'dashboard'])->name('reports.dashboard');

        // Hero slider admin routes
        Route::apiResource('hero-sliders', HeroSliderController::class);
        Route::post('hero-sliders/reorder', [HeroSliderController::class, 'reorder'])->name('hero-sliders.reorder');

        Route::post('media/products', [MediaController::class, 'uploadProducts'])->name('media.products.upload');
        Route::post('media/members/profile', [MediaController::class, 'uploadMemberProfile'])->name('media.members.profile');
        Route::post('media/members/qr-code', [MediaController::class, 'uploadMemberQrCode'])->name('media.members.qr-code');
        Route::post('media/categories/logo', [MediaController::class, 'uploadCategoryLogo'])->name('media.categories.logo');
        Route::post('media/hero-slider', [MediaController::class, 'uploadHeroSlider'])->name('media.hero-slider.upload');
        Route::patch('profile', [AuthController::class, 'updateProfile'])->name('profile.update');

        // Referral routes
        Route::prefix('referral')->name('referral.')->group(function () {
            Route::get('link', [ReferralController::class, 'getReferralLink'])->name('link');
            Route::get('stats', [ReferralController::class, 'getReferralStats'])->name('stats');
        });

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

        Route::prefix('wishlist')->name('wishlist.')->group(function () {
            Route::get('/', [WishlistController::class, 'index'])->name('index');
            Route::post('/', [WishlistController::class, 'store'])->name('store');
            Route::delete('{product}', [WishlistController::class, 'destroy'])->name('destroy');
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

        // MLM Purchase & Income Routes
        Route::prefix('mlm')->name('mlm.')->group(function () {
            Route::post('purchase', [PurchaseController::class, 'processPurchase'])->name('purchase');
            Route::get('income/{member}', [PurchaseController::class, 'getIncomeSummary'])->name('income.summary');
            Route::get('income/{member}/transactions', [PurchaseController::class, 'getIncomeTransactions'])->name('income.transactions');
            Route::get('income/{member}/matching', [PurchaseController::class, 'getMatchingHistory'])->name('income.matching');
            Route::get('income/{member}/wallet', [PurchaseController::class, 'getWalletTransactions'])->name('income.wallet');
            Route::get('income/{member}/bv', [PurchaseController::class, 'getBVTransactions'])->name('income.bv');
            Route::get('income/{member}/statistics', [PurchaseController::class, 'getIncomeStatistics'])->name('income.statistics');
            Route::post('income/adjust', [PurchaseController::class, 'adjustIncome'])->name('income.adjust');
        });

        // KYC Routes
        Route::prefix('kyc')->name('kyc.')->group(function () {
            Route::get('status', [KycController::class, 'status'])->name('status');
            Route::post('update', [KycController::class, 'update'])->name('update');
        });
    });
});
