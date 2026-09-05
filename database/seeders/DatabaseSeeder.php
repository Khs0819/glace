<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@glace.com'],
            [
                'name'     => 'Admin',
                'password' => Hash::make('admin123456'),
            ]
        );

        $this->call([
            MenuCategorySeeder::class,
            FlavorSeeder::class,
            HomeSeeder::class,
            EventSeeder::class,
            AddonSeeder::class,
            ProductSeeder::class,

            // Storefront systems (handoff 10 · 13 · 15 · 16 · 17). All
            // firstOrCreate, so reseeding never overwrites what the shop has
            // since edited in the dashboard.
            DeliveryZoneSeeder::class,
            StorefrontContentSeeder::class,
        ]);
    }
}
