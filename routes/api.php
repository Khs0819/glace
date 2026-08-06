<?php

use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\MenuAddonController;
use App\Http\Controllers\Api\MenuCategoryController;
use App\Http\Controllers\Api\MenuProductController;
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
