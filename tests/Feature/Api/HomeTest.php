<?php

use App\Models\Branch;
use App\Models\HeroSlide;
use App\Models\HomeAbout;
use App\Models\HomeWhyGlace;
use Tests\Support\CatalogFactory;

/**
 * Contract: swagger `IHomePageData`.
 * Handoff 04: absolute URLs, string paragraphs, up to 10 events with real card
 * images, whyGlace features without images. Handoff 08 §أ-4: hero slides.
 */

function heroSlide(array $attributes = []): HeroSlide
{
    return HeroSlide::create(array_merge([
        'man_img'         => 'hero-slides/man.png',
        'piece_img'       => 'hero-slides/piece.png',
        'zigzags_img'     => 'hero-slides/zigzags.png',
        'title_h1'        => 'جلاسية الأمير',
        'title_h2'        => 'لإنتاج الآيس كريم',
        'bg_color'        => '#51C9F4',
        'header_bg_color' => '#51c9f4',
        'h1_bg_color'     => '#53352a',
        'h2_bg_color'     => '#51c9f4',
        'sort_order'      => 1,
    ], $attributes));
}

beforeEach(function () {
    HomeAbout::create([
        'title'      => 'موهوبون في صناعة الأيسكريم !',
        'paragraphs' => ['فقرة أولى', 'فقرة ثانية'],
        'image'      => 'about/character.png',
        'cta_label'  => 'اعرف أكثر',
        'cta_href'   => '/about',
    ]);

    HomeWhyGlace::create([
        'title'           => 'لماذا جلاسيه الأمير؟',
        'description'     => 'شهادة ISO 22000',
        'features'        => [['label' => 'جودة عالية'], ['label' => 'أمانة وثقة']],
        'video_url'       => 'https://www.youtube.com/embed/ShMr0XzIqSM',
        'video_thumbnail' => 'about/video.png',
    ]);

    Branch::create([
        'id'            => 'ramal',
        'label'         => 'فرع الرمال',
        'map_src'       => 'https://www.google.com/maps/embed?x',
        'address'       => 'غزة، الرمال',
        'phone'         => '0592 226 522',
        'whatsapp'      => '0592 226 522',
        'weekday_hours' => 'PM 11:45 – AM 10:00',
        'friday_hours'  => 'PM 11:45 – PM02:00',
        'border_radius' => '32% 68% 69% 31% / 30% 28% 72% 70%',
        'sort_order'    => 1,
    ]);
});

it('returns every top level IHomePageData block', function () {
    heroSlide();

    $this->getJson('/api/home')
        ->assertOk()
        ->assertJsonStructure([
            'hero'     => ['slides'],
            'about'    => ['title', 'paragraphs', 'image', 'ctaLabel', 'ctaHref'],
            'whyGlace' => ['title', 'description', 'features', 'videoUrl', 'videoThumbnail'],
            'branches' => ['title', 'branches'],
            'events'   => ['title', 'items', 'moreLabel', 'moreHref'],
        ]);
});

it('returns about.paragraphs as a plain string array', function () {
    $paragraphs = $this->getJson('/api/home')->assertOk()->json('about.paragraphs');

    expect($paragraphs)->toBe(['فقرة أولى', 'فقرة ثانية']);

    foreach ($paragraphs as $paragraph) {
        expect($paragraph)->toBeString();
    }
});

it('normalizes legacy object paragraphs into strings', function () {
    HomeAbout::first()->update(['paragraphs' => [['text' => 'نص قديم'], ['text' => 'نص آخر']]]);

    expect($this->getJson('/api/home')->json('about.paragraphs'))->toBe(['نص قديم', 'نص آخر']);
});

it('never sends an image on whyGlace features', function () {
    HomeWhyGlace::first()->update([
        'features' => [['label' => 'جودة عالية', 'image' => 'why/quality.png']],
    ]);

    $features = $this->getJson('/api/home')->assertOk()->json('whyGlace.features');

    expect($features[0])->toBe(['label' => 'جودة عالية']);
});

it('returns absolute urls for every hero slide image', function () {
    heroSlide();

    $slide = $this->getJson('/api/home')->assertOk()->json('hero.slides.0');

    foreach (['manImg', 'pieceImg', 'zigzagsImg'] as $key) {
        expect($slide[$key])->toStartWith('http')->toContain('/storage/hero-slides/');
    }

    foreach (['titleH1', 'titleH2', 'bgColor', 'headerBgColor', 'h1BgColor', 'h2BgColor'] as $key) {
        expect($slide)->toHaveKey($key);
    }
});

it('skips hero slides missing any required image instead of emitting nulls', function () {
    heroSlide(['sort_order' => 1]);
    heroSlide(['man_img' => null, 'sort_order' => 2]);
    heroSlide(['zigzags_img' => null, 'sort_order' => 3]);

    $slides = $this->getJson('/api/home')->assertOk()->json('hero.slides');

    expect($slides)->toHaveCount(1)
        ->and($slides[0]['manImg'])->not->toBeNull();
});

it('returns up to ten latest events with populated card images', function () {
    foreach (range(1, 12) as $n) {
        CatalogFactory::event(['title' => 'فعالية ' . $n]);
    }

    $items = $this->getJson('/api/home')->assertOk()->json('events.items');

    expect($items)->toHaveCount(10);

    foreach ($items as $item) {
        expect($item['image'])->toStartWith('http')
            ->and($item['href'])->toBe('/events/' . $item['id']);
        foreach (['id', 'title', 'image', 'href'] as $key) {
            expect($item)->toHaveKey($key);
        }
    }

    // Newest first.
    expect($items[0]['title'])->toBe('فعالية 12');
});

it('uses the same card image source as the events endpoint', function () {
    $event = CatalogFactory::event(['list_image' => null]);
    CatalogFactory::eventImage($event, 'event-images/gallery-1.png');

    $homeImage = $this->getJson('/api/home')->json('events.items.0.image');
    $listImage = $this->getJson('/api/events/' . $event->id)->json('listImage');

    expect($homeImage)->toBe($listImage)->not->toBeNull();
});
