<?php

namespace Database\Seeders;

use App\Models\Addon;
use Illuminate\Database\Seeder;

class AddonSeeder extends Seeder
{
    public function run(): void
    {
        // Shared catalog — product_id IS NULL
        Addon::whereNull('product_id')->delete();

        $sharedAddons = [
            ['slug' => 'extra-biscuit', 'label' => 'بسكوت مخروط',    'price' => 3, 'available' => true, 'type' => 'counter', 'max_qty' => 10, 'sort_order' => 1],
            ['slug' => 'extra-caramel', 'label' => 'صوص كراميل',      'price' => 3, 'available' => true, 'type' => 'toggle',  'max_qty' => null, 'sort_order' => 2],
            ['slug' => 'extra-nuts',    'label' => 'بندق مبشور',       'price' => 4, 'available' => true, 'type' => 'toggle',  'max_qty' => null, 'sort_order' => 3],
            ['slug' => 'extra-nutella', 'label' => 'صوص نوتيلا إضافي','price' => 4, 'available' => true, 'type' => 'toggle',  'max_qty' => null, 'sort_order' => 4],
            ['slug' => 'extra-oreo',    'label' => 'قطع أوريو',        'price' => 3, 'available' => true, 'type' => 'toggle',  'max_qty' => null, 'sort_order' => 5],
            ['slug' => 'extra-lotus',   'label' => 'بسكوت لوتس',       'price' => 4, 'available' => true, 'type' => 'toggle',  'max_qty' => null, 'sort_order' => 6],
            ['slug' => 'extra-cream',   'label' => 'كريمة مخفوقة',     'price' => 2, 'available' => true, 'type' => 'toggle',  'max_qty' => null, 'sort_order' => 7],
        ];

        foreach ($sharedAddons as $data) {
            Addon::create(array_merge($data, ['product_id' => null]));
        }
    }
}
