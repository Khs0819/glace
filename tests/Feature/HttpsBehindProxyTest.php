<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;
use Tests\Support\CatalogFactory;

/**
 * The app runs behind a TLS-terminating reverse proxy. If Laravel does not learn
 * that the original request was HTTPS, it emits http:// asset URLs and the
 * browser blocks them as mixed content — which silently kills Filament's
 * select / textarea / file-upload controls in the dashboard.
 */

it('treats a forwarded https request as secure', function () {
    $response = $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host'  => 'back.glaceelameer.com',
    ])->get('/api/menu/categories');

    $response->assertOk();

    expect(request()->isSecure())->toBeTrue();
});

it('emits https asset urls for the admin panel when forwarded as https', function () {
    $this->actingAs(User::factory()->create());

    $html = $this->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('/admin/products')
        ->assertSuccessful()
        ->getContent();

    // Filament loads its form components as ES modules; a single http:// src is
    // enough for the browser to block the whole control.
    expect($html)->not->toContain('http://' . request()->getHost() . '/js/filament');
});

it('forces https on generated urls when APP_URL is https', function () {
    config(['app.url' => 'https://back.glaceelameer.com']);
    (new App\Providers\AppServiceProvider(app()))->boot();

    expect(URL::to('/admin'))->toStartWith('https://');
});

it('serves media over https when APP_URL is https', function () {
    config(['filesystems.disks.public.url' => 'https://back.glaceelameer.com/storage']);

    $product = CatalogFactory::flatList();
    CatalogFactory::item($product, 'nutella', ['image' => 'items/nutella.png']);

    $this->getJson('/api/menu/products/pancake')
        ->assertOk()
        ->assertJsonPath('items.0.image', 'https://back.glaceelameer.com/storage/items/nutella.png');
});
