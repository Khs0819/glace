<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Remove the "data" wrapper from all JSON API resources
        // so responses match the frontend expected shape (plain arrays/objects)
        JsonResource::withoutWrapping();

        // Second line of defence behind the reverse proxy: if the site is served
        // over HTTPS but the proxy does not forward X-Forwarded-Proto, trusting it
        // is not enough and Laravel would still emit http:// links. Deriving the
        // scheme from APP_URL keeps every generated URL — assets, routes and the
        // public disk's /storage URLs — on the scheme the site is actually served
        // on, so nothing is blocked as mixed content.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}