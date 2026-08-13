<?php

use Tests\Support\CatalogFactory;

/**
 * Contract: swagger `IEvent` / `IEventsListResponse`.
 * Handoff 04 §5: `images` is the detail gallery — absolute URLs, no null
 * entries, `[]` when empty; managed from Filament, not the card image alone.
 */

it('returns the paginated list envelope', function () {
    foreach (range(1, 12) as $n) {
        CatalogFactory::event(['title' => 'فعالية ' . $n]);
    }

    $this->getJson('/api/events?perPage=8&page=2')
        ->assertOk()
        ->assertJsonStructure(['items', 'total', 'page', 'perPage', 'totalPages'])
        ->assertJsonPath('total', 12)
        ->assertJsonPath('page', 2)
        ->assertJsonPath('perPage', 8)
        ->assertJsonPath('totalPages', 2)
        ->assertJsonCount(4, 'items');
});

it('returns every required IEvent field on list and detail', function () {
    $event = CatalogFactory::event();
    CatalogFactory::eventImage($event, 'event-images/a.png');

    foreach ([$this->getJson('/api/events')->json('items.0'), $this->getJson("/api/events/{$event->id}")->json()] as $payload) {
        foreach (['id', 'title', 'date', 'description', 'listImage', 'images'] as $key) {
            expect($payload)->toHaveKey($key);
        }
        expect($payload['images'])->toBeArray();
    }
});

it('returns the gallery as absolute urls in sort order', function () {
    $event = CatalogFactory::event();
    CatalogFactory::eventImage($event, 'event-images/b.png', 2);
    CatalogFactory::eventImage($event, 'event-images/a.png', 1);

    $images = $this->getJson("/api/events/{$event->id}")->assertOk()->json('images');

    expect($images)->toHaveCount(2)
        ->and($images[0])->toContain('/storage/event-images/a.png')
        ->and($images[1])->toContain('/storage/event-images/b.png');

    foreach ($images as $image) {
        expect($image)->toStartWith('http');
    }
});

it('returns an empty array rather than nulls when the gallery is unusable', function () {
    $event = CatalogFactory::event();
    CatalogFactory::eventImage($event, 'https://cdn.example.com/dead.png');
    CatalogFactory::eventImage($event, '');

    $images = $this->getJson("/api/events/{$event->id}")->assertOk()->json('images');

    expect($images)->toBe([]);
});

it('falls back to the first gallery image when no card image was uploaded', function () {
    $event = CatalogFactory::event(['list_image' => null]);
    CatalogFactory::eventImage($event, 'event-images/first.png', 1);
    CatalogFactory::eventImage($event, 'event-images/second.png', 2);

    $this->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('listImage', fn ($url) => str_contains($url, '/storage/event-images/first.png'));
});

it('prefers the uploaded card image over the gallery', function () {
    $event = CatalogFactory::event(['list_image' => 'events/card.png']);
    CatalogFactory::eventImage($event, 'event-images/gallery.png');

    $this->getJson("/api/events/{$event->id}")
        ->assertOk()
        ->assertJsonPath('listImage', fn ($url) => str_contains($url, '/storage/events/card.png'));
});

it('returns a json 404 for an unknown event', function () {
    $this->getJson('/api/events/999999')
        ->assertStatus(404)
        ->assertJsonStructure(['message']);
});

it('caps perPage at the documented maximum', function () {
    CatalogFactory::event();

    $this->getJson('/api/events?perPage=500')
        ->assertOk()
        ->assertJsonPath('perPage', 50);
});
