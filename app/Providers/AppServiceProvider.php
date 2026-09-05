<?php

namespace App\Providers;

use App\Services\Auth\CustomerAuthService;
use App\Services\JawwalPay\JawwalPayClient;
use App\Services\Sms\SmsSender;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One place reads the gateway credentials, so nothing else has to know
        // where they live or re-derive them per call.
        $this->app->singleton(
            JawwalPayClient::class,
            fn () => new JawwalPayClient(config('services.jawwalpay', [])),
        );

        // Same reasoning: the SMS gateway's settings are read in one place.
        $this->app->singleton(
            SmsSender::class,
            fn () => new SmsSender(config('services.sms', [])),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Remove the "data" wrapper from all JSON API resources
        // so responses match the frontend expected shape (plain arrays/objects)
        JsonResource::withoutWrapping();

        // The storefront sends `Authorization: <token>` with no `Bearer` prefix
        // (swagger.yaml, securitySchemes.bearerAuth). Sanctum's guard would not
        // recognise that, so the lookup is ours: hash the presented token and
        // find its row in customer_tokens.
        Auth::viaRequest('customer-token', function (Request $request) {
            $auth = $this->app->make(CustomerAuthService::class);

            return $auth->resolveToken($auth->tokenFromRequest($request));
        });

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