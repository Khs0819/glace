<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\MenuAddonController;
use App\Http\Controllers\Api\MenuCategoryController;
use App\Http\Controllers\Api\MenuProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderPaymentController;
use App\Http\Controllers\Api\StorefrontOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes (no auth required)
|--------------------------------------------------------------------------
*/

// Home page aggregate
Route::get('/home', HomeController::class);

// Events
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{id}', [EventController::class, 'show'])->whereNumber('id');

// Contact
Route::post('/contact', [ContactController::class, 'store']);

// Menu
Route::prefix('menu')->group(function () {
    Route::get('/categories', [MenuCategoryController::class, 'index']);
    Route::get('/products', [MenuProductController::class, 'index']);
    Route::get('/products/{slug}', [MenuProductController::class, 'show']);
    Route::get('/addons', [MenuAddonController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| Checkout & payment (Jawwal Pay)
|--------------------------------------------------------------------------
|
| Carts are priced here, never on the client. Everything about an existing
| order is authorised by the `token` returned once when it was created.
| Throttles are per IP and sit in front of the gateway, which charges us
| per call and rate-limits us on its own side.
|
*/

Route::post('/checkout/quote', [OrderController::class, 'quote'])->middleware('throttle:60,1');

// The original guest checkout: a JSON body with a `customer` object, priced
// and then paid through the Jawwal Pay OTP flow below. Superseded for the
// storefront by POST /orders (handoff 12), which is why it moved off that path
// — but kept working, because it is the flow the payment routes drive.
Route::post('/checkout/orders', [OrderController::class, 'store'])->middleware('throttle:20,1');
Route::get('/checkout/orders/{order}', [OrderController::class, 'show']);

// Pay an order that already exists. Distinct from the storefront's automatic
// `jawwal` method, which proves its code *before* the order is created
// (POST /orders/jawwal/send-code).
Route::prefix('orders/{order}/payment')->middleware('throttle:10,1')->group(function () {
    Route::post('/otp', [OrderPaymentController::class, 'requestOtp']);
    Route::post('/confirm', [OrderPaymentController::class, 'confirm']);
});


/*
|--------------------------------------------------------------------------
| Storefront accounts (handoff 08 · 09)
|--------------------------------------------------------------------------
|
| Passwordless. A phone number plus a code texted to it is the entire
| credential story: there is no password, no reset, and no logout endpoint
| (the storefront drops its own token — handoff 09).
|
| Throttles here are per IP and sit in front of the per-number cooldown in
| OtpService. Both are needed: the route throttle stops one host hammering
| many numbers, the database cooldown stops many hosts hammering one number.
|
*/

Route::prefix('auth')->group(function () {
    Route::post('/otp/send', [AuthController::class, 'sendOtp'])->middleware('throttle:10,1');
    Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:20,1');

    Route::middleware('customer')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
    });
});

/*
|--------------------------------------------------------------------------
| Saved addresses & delivery zones (handoff 10)
|--------------------------------------------------------------------------
*/

// Public: checkout needs delivery fees before anyone signs in.
Route::get('/addresses/delivery-zones', [AddressController::class, 'zones']);

Route::middleware('customer')->prefix('addresses')->group(function () {
    Route::get('/', [AddressController::class, 'index']);
    Route::post('/', [AddressController::class, 'store']);
    Route::put('/{id}', [AddressController::class, 'update']);
    Route::delete('/{id}', [AddressController::class, 'destroy']);
    Route::post('/{id}/default', [AddressController::class, 'makeDefault']);
});

/*
|--------------------------------------------------------------------------
| Coupons (handoff 11)
|--------------------------------------------------------------------------
|
| A preview for the cart screen. Nothing is reserved or spent here — POST
| /orders re-evaluates the code from scratch and ignores any discount the
| client sends.
|
| Optional auth: the cart works signed out, but a per-customer usage limit
| can only be checked when we know who is asking.
|
*/

Route::post('/cart/apply-coupon', [CouponController::class, 'apply'])
    ->middleware(['customer.optional', 'throttle:30,1']);

/*
|--------------------------------------------------------------------------
| Wallet (handoff 14)
|--------------------------------------------------------------------------
|
| Note what is absent: nothing here approves a top-up. A request stays
| "قيد المراجعة" until an admin approves it in Filament — the frontend used
| to expose that decision to the browser console.
|
*/

Route::middleware('customer')->prefix('wallet')->group(function () {
    Route::get('/', [WalletController::class, 'show']);
    Route::post('/deduct', [WalletController::class, 'deduct']);
    Route::get('/topup-requests', [WalletController::class, 'topUpRequests']);
    Route::post('/topup-requests', [WalletController::class, 'storeTopUpRequest'])->middleware('throttle:20,1');
});

/*
|--------------------------------------------------------------------------
| Dashboard-owned content (handoff 13 · 15 · 16 · 17)
|--------------------------------------------------------------------------
|
| All of this used to be hardcoded in the storefront bundle. Public, cached
| by the client, nothing customer-specific.
|
*/

Route::get('/payment-accounts', [ContentController::class, 'paymentAccounts']);
Route::get('/help/faqs', [ContentController::class, 'faqs']);
Route::get('/terms', [ContentController::class, 'terms']);
Route::get('/privacy', [ContentController::class, 'privacy']);

/*
|--------------------------------------------------------------------------
| Storefront orders (handoff 12)
|--------------------------------------------------------------------------
|
| POST /orders arrives as multipart/form-data — `receiptImage` is a real file
| and `items` a JSON string — and the server re-prices the whole cart from the
| catalog. No amount on the request is read.
|
| Creation takes `customer.optional`: an order placed while signed in is bound
| to the account, and a guest still gets a one-time token back so they can
| track what they just ordered.
|
*/

// Before /orders/{reference}, or "jawwal" would be read as an order id.
Route::post('/orders/jawwal/send-code', [StorefrontOrderController::class, 'sendJawwalCode'])
    ->middleware(['customer.optional', 'throttle:10,1']);

Route::post('/orders', [StorefrontOrderController::class, 'store'])
    ->middleware(['customer.optional', 'throttle:20,1']);

Route::middleware('customer')->group(function () {
    Route::get('/orders', [StorefrontOrderController::class, 'index']);
});

// Readable by the owning customer, or by a guest holding the order's token.
Route::middleware('customer.optional')->group(function () {
    Route::get('/orders/{reference}', [StorefrontOrderController::class, 'show']);
    Route::post('/orders/{reference}/cancel', [StorefrontOrderController::class, 'cancel']);
    Route::post('/orders/{reference}/receipt', [StorefrontOrderController::class, 'receipt']);
    Route::post('/orders/{reference}/received', [StorefrontOrderController::class, 'received']);
    Route::post('/orders/{reference}/email-summary', [StorefrontOrderController::class, 'emailSummary'])
        ->middleware('throttle:10,1');
});
