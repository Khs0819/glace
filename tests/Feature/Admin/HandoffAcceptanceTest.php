<?php

use App\Filament\Resources\EventResource;
use App\Filament\Resources\ProductResource;
use App\Models\User;
use Tests\Support\CatalogFactory;

/**
 * Acceptance checks written directly from docs/backend-handoff (2026-08-13).
 *
 * These assert the panels are actually RENDERED on the edit screens — the
 * tickets are all "مفيش تبويب/حقل في Filament", so a class existing on disk is
 * not evidence. Only what the admin can see counts.
 */

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('ticket 01: shows the variants panel on a flat-list edit screen', function () {
    $product = CatalogFactory::flatList();
    CatalogFactory::item($product, 'nutella');

    $this->get(ProductResource::getUrl('edit', ['record' => $product]))
        ->assertSuccessful()
        ->assertSee('الأصناف (Flat-List)')
        ->assertSee('قواعد المكس (Flat-List)');
});

it('ticket 05: shows types, sizes and prices panels on a builder edit screen', function () {
    $product = CatalogFactory::builder();
    CatalogFactory::container($product, 'cup');
    CatalogFactory::size($product, 'cup-small');

    $this->get(ProductResource::getUrl('edit', ['record' => $product]))
        ->assertSuccessful()
        ->assertSee('الأنواع / الحاويات (Builder)')
        ->assertSee('الأحجام والأسعار (Builder)');
});

it('ticket 05: shows the ice cream addon prices panel on brad-boza', function () {
    $product = CatalogFactory::builder('brad-boza', ['includes_ice_cream_step' => true]);
    CatalogFactory::size($product, 'brad-boza-small');

    $this->get(ProductResource::getUrl('edit', ['record' => $product]))
        ->assertSuccessful()
        ->assertSee('أسعار إضافة البوظة (براد مع بوظة)');
});

it('ticket 04: shows the gallery panel on the event edit screen, separate from the card image', function () {
    $event = CatalogFactory::event();

    // A single relation manager renders lazily with no server-side tab bar, so
    // assert the panel is mounted plus the card-image field is a separate control.
    $this->get(EventResource::getUrl('edit', ['record' => $event]))
        ->assertSuccessful()
        ->assertSee('fi-resource-relation-manager', escape: false)
        ->assertSee('صورة البطاقة');

    expect(EventResource::getRelations())
        ->toContain(EventResource\RelationManagers\EventImagesRelationManager::class);
});

it('does not show builder panels on flat-list products or vice versa', function () {
    $flat = CatalogFactory::flatList();
    $builder = CatalogFactory::builder();

    $this->get(ProductResource::getUrl('edit', ['record' => $flat]))
        ->assertSuccessful()
        ->assertDontSee('الأحجام والأسعار (Builder)');

    $this->get(ProductResource::getUrl('edit', ['record' => $builder]))
        ->assertSuccessful()
        ->assertDontSee('الأصناف (Flat-List)');
});
