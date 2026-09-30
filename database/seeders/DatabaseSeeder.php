<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * No account is seeded here any more.
         *
         * This used to create admin@glace.com with the password "admin123456",
         * and `role` defaults to manager — so every install that ran the
         * seeder to get its menu also published a full-access dashboard login
         * that anybody could guess. Staff accounts are made deliberately, with
         * a password that exists only once:
         *
         *     php artisan staff:setup
         */
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
