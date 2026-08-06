<?php

namespace Database\Seeders;

use App\Models\Flavor;
use Illuminate\Database\Seeder;

class FlavorSeeder extends Seeder
{
    public function run(): void
    {
        $flavors = [
            // Classic (13)
            ['id' => 'chocolate',       'name_ar' => 'شوكولاتة',       'name_en' => 'Chocolate',       'image' => 'https://cdn.example.com/flavors/chocolate.jpg',       'family' => 'classic', 'available' => true,  'is_premium_mix_flavor' => false],
            ['id' => 'vanilla',         'name_ar' => 'فانيلا',         'name_en' => 'Vanilla',         'image' => 'https://cdn.example.com/flavors/vanilla.jpg',         'family' => 'classic', 'available' => true,  'is_premium_mix_flavor' => false],
            ['id' => 'strawberry',      'name_ar' => 'فراولة',         'name_en' => 'Strawberry',      'image' => 'https://cdn.example.com/flavors/strawberry.jpg',      'family' => 'classic', 'available' => true,  'is_premium_mix_flavor' => false],
            ['id' => 'caramel',         'name_ar' => 'كراميل',         'name_en' => 'Caramel',         'image' => 'https://cdn.example.com/flavors/caramel.jpg',         'family' => 'classic', 'available' => true,  'is_premium_mix_flavor' => false],
            ['id' => 'dark-chocolate',  'name_ar' => 'شوكولاتة داكنة', 'name_en' => 'Dark Chocolate',  'image' => 'https://cdn.example.com/flavors/dark-chocolate.jpg',  'family' => 'classic', 'available' => true,  'is_premium_mix_flavor' => false],
            ['id' => 'nescafe',         'name_ar' => 'نسكافيه',        'name_en' => 'Nescafe',         'image' => 'https://cdn.example.com/flavors/nescafe.jpg',         'family' => 'classic', 'available' => true,  'is_premium_mix_flavor' => false],
            ['id' => 'coconut',         'name_ar' => 'جوز هند',        'name_en' => 'Coconut',         'image' => 'https://cdn.example.com/flavors/coconut.jpg',         'family' => 'classic', 'available' => true,  'is_premium_mix_flavor' => false],
            ['id' => 'mango',           'name_ar' => 'مانجا',          'name_en' => 'Mango',           'image' => 'https://cdn.example.com/flavors/mango.jpg',           'family' => 'classic', 'available' => false, 'is_premium_mix_flavor' => false],
            ['id' => 'banana',          'name_ar' => 'موز',            'name_en' => 'Banana',          'image' => 'https://cdn.example.com/flavors/banana.jpg',          'family' => 'classic', 'available' => true,  'is_premium_mix_flavor' => false],
            ['id' => 'grape',           'name_ar' => 'عنب',            'name_en' => 'Grape',           'image' => 'https://cdn.example.com/flavors/grape.jpg',           'family' => 'classic', 'available' => true,  'is_premium_mix_flavor' => false],
            ['id' => 'bazooka',         'name_ar' => 'بازوكا',         'name_en' => 'Bazooka',         'image' => 'https://cdn.example.com/flavors/bazooka.jpg',         'family' => 'classic', 'available' => true,  'is_premium_mix_flavor' => false],
            ['id' => 'mario',           'name_ar' => 'ماريو',          'name_en' => 'Mario',           'image' => 'https://cdn.example.com/flavors/mario.jpg',           'family' => 'classic', 'available' => true,  'is_premium_mix_flavor' => false],
            ['id' => 'lemon',           'name_ar' => 'ليمون',          'name_en' => 'Lemon',           'image' => 'https://cdn.example.com/flavors/lemon.jpg',           'family' => 'classic', 'available' => true,  'is_premium_mix_flavor' => false],

            // Stevia (2) — folds into classic picker
            ['id' => 'vanilla-stevia',  'name_ar' => 'فانيلا ستيفيا',  'name_en' => 'Vanilla Stevia',  'image' => 'https://cdn.example.com/flavors/vanilla-stevia.jpg',  'family' => 'stevia',  'available' => true,  'is_premium_mix_flavor' => false],
            ['id' => 'nescafe-stevia',  'name_ar' => 'نسكافيه ستيفيا', 'name_en' => 'Nescafe Stevia',  'image' => 'https://cdn.example.com/flavors/nescafe-stevia.jpg',  'family' => 'stevia',  'available' => true,  'is_premium_mix_flavor' => false],

            // Special (8)
            ['id' => 'arabian',         'name_ar' => 'عربية',          'name_en' => 'Arabian',         'image' => 'https://cdn.example.com/flavors/arabian.jpg',         'family' => 'special', 'available' => true,  'is_premium_mix_flavor' => false],
            ['id' => 'nutella',         'name_ar' => 'نوتيلا',         'name_en' => 'Nutella',         'image' => 'https://cdn.example.com/flavors/nutella.jpg',         'family' => 'special', 'available' => true,  'is_premium_mix_flavor' => false],
            ['id' => 'oreo',            'name_ar' => 'أوريو',          'name_en' => 'Oreo',            'image' => 'https://cdn.example.com/flavors/oreo.jpg',            'family' => 'special', 'available' => true,  'is_premium_mix_flavor' => false],
            ['id' => 'kitkat',          'name_ar' => 'كت كات',         'name_en' => 'KitKat',          'image' => 'https://cdn.example.com/flavors/kitkat.jpg',          'family' => 'special', 'available' => true,  'is_premium_mix_flavor' => false],
            ['id' => 'flora',           'name_ar' => 'فلورا',          'name_en' => 'Flora',           'image' => 'https://cdn.example.com/flavors/flora.jpg',           'family' => 'special', 'available' => false, 'is_premium_mix_flavor' => false],
            ['id' => 'kinder',          'name_ar' => 'كندر',           'name_en' => 'Kinder',          'image' => 'https://cdn.example.com/flavors/kinder.jpg',          'family' => 'special', 'available' => true,  'is_premium_mix_flavor' => false],
            ['id' => 'lotus',           'name_ar' => 'لوتس',           'name_en' => 'Lotus',           'image' => 'https://cdn.example.com/flavors/lotus.jpg',           'family' => 'special', 'available' => true,  'is_premium_mix_flavor' => false],
            ['id' => 'pistachio',       'name_ar' => 'بيستاشيو',       'name_en' => 'Pistachio',       'image' => 'https://cdn.example.com/flavors/pistachio.jpg',       'family' => 'special', 'available' => true,  'is_premium_mix_flavor' => true],
        ];

        foreach ($flavors as $data) {
            Flavor::updateOrCreate(['id' => $data['id']], $data);
        }
    }
}
