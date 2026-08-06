<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MenuCategorySeeder::class,
            FlavorSeeder::class,
            HomeSeeder::class,
            EventSeeder::class,
            AddonSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
