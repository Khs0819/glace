<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The check that catches a database and a disk that have parted company —
 * the failure the dashboard's own "no image" filter is blind to, because the
 * row it inspects is perfectly well formed.
 */

beforeEach(function () {
    Storage::fake('public');
});

it('names a row whose file is gone', function () {
    DB::table('hero_slides')->insert([
        'man_img' => 'items/gone.webp',
        'title_h1' => 'جلاسيه', 'title_h2' => 'الأمير',
        'bg_color' => '#fff', 'header_bg_color' => '#fff',
        'h1_bg_color' => '#fff', 'h2_bg_color' => '#fff',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('media:check --list')
        ->expectsOutputToContain('items/gone.webp')
        ->assertFailed();
});

it('passes when the file is really there', function () {
    Storage::disk('public')->put('items/here.webp', 'x');

    DB::table('hero_slides')->insert([
        'man_img' => 'items/here.webp',
        'title_h1' => 'جلاسيه', 'title_h2' => 'الأمير',
        'bg_color' => '#fff', 'header_bg_color' => '#fff',
        'h1_bg_color' => '#fff', 'h2_bg_color' => '#fff',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('media:check')->assertSuccessful();
});

it('does not report an externally hosted image as missing', function () {
    DB::table('hero_slides')->insert([
        'man_img' => 'https://cdn.example.com/menu/tub.jpg',
        'title_h1' => 'جلاسيه', 'title_h2' => 'الأمير',
        'bg_color' => '#fff', 'header_bg_color' => '#fff',
        'h1_bg_color' => '#fff', 'h2_bg_color' => '#fff',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Somebody else's uptime, and nothing we could re-upload.
    $this->artisan('media:check')->assertSuccessful();
});

it('says to fix the volume before re-uploading', function () {
    DB::table('hero_slides')->insert([
        'man_img' => 'items/gone.webp',
        'title_h1' => 'جلاسيه', 'title_h2' => 'الأمير',
        'bg_color' => '#fff', 'header_bg_color' => '#fff',
        'h1_bg_color' => '#fff', 'h2_bg_color' => '#fff',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Re-uploading first means doing it twice.
    $this->artisan('media:check')
        ->expectsOutputToContain('Mount a volume')
        ->assertFailed();
});
