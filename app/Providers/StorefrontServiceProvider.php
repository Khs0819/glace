<?php

namespace App\Providers;

use App\Services\Printing\EscPosPrinter;
use App\Services\Printing\ReceiptPrinter;
use App\Services\Storefront\DeliveryRestrictions;
use Illuminate\Support\ServiceProvider;

/**
 * Storefront services whose construction needs configuration.
 */
class StorefrontServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The delivery rules are the shop's, not the system's, so they come
        // from config rather than being baked into the class.
        $this->app->singleton(
            DeliveryRestrictions::class,
            fn () => new DeliveryRestrictions(config('storefront.delivery', [])),
        );

        // The printer's settings describe one physical device, so they are read
        // in one place rather than passed down from every caller.
        $this->app->singleton(
            EscPosPrinter::class,
            fn () => new EscPosPrinter(config('storefront.printer', [])),
        );

        $this->app->singleton(
            ReceiptPrinter::class,
            fn ($app) => new ReceiptPrinter(
                $app->make(EscPosPrinter::class),
                config('storefront.shop', []),
            ),
        );
    }
}
