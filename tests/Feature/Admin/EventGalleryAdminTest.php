<?php

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventResource\Pages\EditEvent;
use App\Filament\Resources\EventResource\RelationManagers\EventImagesRelationManager;
use App\Models\EventImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CatalogFactory;

/**
 * Handoff 04 §5 / 08 §ب-4 — the single-event gallery (`IEvent.images`) needs a
 * dashboard surface that is separate from «صورة البطاقة» (listImage), otherwise
 * the API returns `[]` forever.
 */

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->event = CatalogFactory::event();
});

function galleryManager($event)
{
    return Livewire::test(EventImagesRelationManager::class, [
        'ownerRecord' => $event,
        'pageClass'   => EditEvent::class,
    ]);
}

it('uploads gallery images that the api returns as absolute urls', function () {
    fakePublicDisk();

    foreach (['a.png', 'b.png'] as $index => $name) {
        galleryManager($this->event)
            ->callTableAction('create', data: [
                'image_url'  => [UploadedFile::fake()->image($name)],
                'sort_order' => $index + 1,
            ])
            ->assertHasNoTableActionErrors();
    }

    expect(EventImage::count())->toBe(2);

    $images = $this->getJson("/api/events/{$this->event->id}")->assertOk()->json('images');

    expect($images)->toHaveCount(2);

    foreach ($images as $image) {
        expect($image)->toStartWith('http')->toContain('/storage/event-images/');
    }
});

it('keeps the gallery separate from the card image', function () {
    fakePublicDisk();

    galleryManager($this->event)
        ->callTableAction('create', data: [
            'image_url'  => [UploadedFile::fake()->image('gallery.png')],
            'sort_order' => 1,
        ])
        ->assertHasNoTableActionErrors();

    $payload = $this->getJson("/api/events/{$this->event->id}")->assertOk()->json();

    expect($payload['listImage'])->toContain('/storage/events/card.png')
        ->and($payload['images'][0])->toContain('/storage/event-images/')
        ->and($payload['images'][0])->not->toBe($payload['listImage']);
});

it('reorders the gallery from the dashboard', function () {
    fakePublicDisk();

    // The upload field only rehydrates files that exist on the disk.
    $first = CatalogFactory::eventImage($this->event, 'event-images/first.png', 1);
    CatalogFactory::eventImage($this->event, 'event-images/second.png', 2);
    Storage::disk('public')->put('event-images/first.png', 'x');
    Storage::disk('public')->put('event-images/second.png', 'x');

    // Only the position changes; the mounted upload field keeps its value.
    galleryManager($this->event)
        ->callTableAction('edit', $first, data: ['sort_order' => 3])
        ->assertHasNoTableActionErrors();

    $images = $this->getJson("/api/events/{$this->event->id}")->json('images');

    expect($images[0])->toContain('second.png')
        ->and($images[1])->toContain('first.png');
});

it('removes a gallery image from the dashboard', function () {
    $image = CatalogFactory::eventImage($this->event, 'event-images/a.png');

    galleryManager($this->event)->callTableAction('delete', $image);

    expect(EventImage::count())->toBe(0)
        ->and($this->getJson("/api/events/{$this->event->id}")->json('images'))->toBe([]);
});

it('deletes the gallery together with its event', function () {
    CatalogFactory::eventImage($this->event, 'event-images/a.png');

    $this->event->delete();

    expect(EventImage::count())->toBe(0);
});

it('flags events that still have no card image', function () {
    expect(EventResource::getNavigationBadge())->toBeNull();

    CatalogFactory::event(['list_image' => null]);
    CatalogFactory::event(['list_image' => null]);

    expect(EventResource::getNavigationBadge())->toBe('2 بلا صورة');
});
