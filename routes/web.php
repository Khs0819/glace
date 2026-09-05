<?php

use App\Http\Controllers\Admin\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

/*
|--------------------------------------------------------------------------
| Counter receipts
|--------------------------------------------------------------------------
|
| Staff screens, behind the dashboard's own session auth — a receipt carries a
| customer's name, phone and address, so none of this may be reachable by a
| storefront token or by nobody at all.
|
| `auth` alone is not enough here: the panel's authenticated user is the one
| these pages belong to, and Filament's own middleware stack is what puts it
| there.
|
*/

Route::middleware(['web', 'auth'])->prefix('admin/receipts')->name('receipts.')->group(function () {
    // Polled by the cashier screen for orders still needing attention.
    Route::get('/queue', [ReceiptController::class, 'queue'])->name('queue');

    // The printable slip. `?auto=1` prints on load and marks the order printed.
    Route::get('/{reference}', [ReceiptController::class, 'show'])->name('show');

    Route::post('/{reference}/reprint', [ReceiptController::class, 'reprint'])->name('reprint');
});
