<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use Illuminate\Database\Seeder;

class MenuCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['id' => 'ice-cream',   'label' => 'بوظة',             'icon' => 'ice-cream',  'accent_color' => '#5bc8f5', 'gradient_from' => '#5bc8f5', 'gradient_to' => '#1e9fc7', 'sort_order' => 1],
            ['id' => 'brad',        'label' => 'براد',             'icon' => 'cup-soda',   'accent_color' => '#6bd9b4', 'gradient_from' => '#6bd9b4', 'gradient_to' => '#2eaf88', 'sort_order' => 2],
            ['id' => 'brad-boza',   'label' => 'براد مع بوظة',     'icon' => 'cup-soda',   'accent_color' => '#3dbfa0', 'gradient_from' => '#3dbfa0', 'gradient_to' => '#1a9479', 'sort_order' => 3],
            ['id' => 'milkshake',   'label' => 'ميلك شيك',         'icon' => 'milk',       'accent_color' => '#f48fb1', 'gradient_from' => '#f48fb1', 'gradient_to' => '#c2185b', 'sort_order' => 4],
            ['id' => 'kunafa',      'label' => 'كنافة آيس كريم',   'icon' => 'cake',       'accent_color' => '#a1887f', 'gradient_from' => '#a1887f', 'gradient_to' => '#6d4c41', 'sort_order' => 5],
            ['id' => 'loqaimat',    'label' => 'لقيمات',           'icon' => 'cake',       'accent_color' => '#ffb74d', 'gradient_from' => '#ffb74d', 'gradient_to' => '#e65100', 'sort_order' => 6],
            ['id' => 'pancake',     'label' => 'بان كيك',          'icon' => 'cake',       'accent_color' => '#f4a851', 'gradient_from' => '#f4a851', 'gradient_to' => '#c97d2a', 'sort_order' => 7],
            ['id' => 'waffle',      'label' => 'وافل',             'icon' => 'cake',       'accent_color' => '#ffcc02', 'gradient_from' => '#ffcc02', 'gradient_to' => '#c9a000', 'sort_order' => 8],
            ['id' => 'crepe',       'label' => 'كريب',             'icon' => 'cake',       'accent_color' => '#ffab91', 'gradient_from' => '#ffab91', 'gradient_to' => '#bf360c', 'sort_order' => 9],
            ['id' => 'pizza',       'label' => 'بيتزا جلاسيه',     'icon' => 'cake',       'accent_color' => '#ef5350', 'gradient_from' => '#ef5350', 'gradient_to' => '#b71c1c', 'sort_order' => 10],
            ['id' => 'molten',      'label' => 'مولتن كيك',        'icon' => 'cake',       'accent_color' => '#795548', 'gradient_from' => '#795548', 'gradient_to' => '#3e2723', 'sort_order' => 11],
            ['id' => 'desserts',    'label' => 'حلويات',           'icon' => 'cake',       'accent_color' => '#ba68c8', 'gradient_from' => '#ba68c8', 'gradient_to' => '#6a1b9a', 'sort_order' => 12],
            ['id' => 'cold-drinks', 'label' => 'مشروبات باردة',    'icon' => 'glass-water','accent_color' => '#4fc3f7', 'gradient_from' => '#4fc3f7', 'gradient_to' => '#0277bd', 'sort_order' => 13],
            ['id' => 'hot-drinks',  'label' => 'مشروبات ساخنة',    'icon' => 'milk',       'accent_color' => '#ff8a65', 'gradient_from' => '#ff8a65', 'gradient_to' => '#bf360c', 'sort_order' => 14],
            ['id' => 'juices',      'label' => 'عصائر طبيعية',     'icon' => 'apple',      'accent_color' => '#ffa726', 'gradient_from' => '#ffa726', 'gradient_to' => '#e65100', 'sort_order' => 15],
            ['id' => 'corn',        'label' => 'ذرة',              'icon' => 'apple',      'accent_color' => '#ffca28', 'gradient_from' => '#ffca28', 'gradient_to' => '#f57f17', 'sort_order' => 16],
        ];

        foreach ($categories as $data) {
            MenuCategory::updateOrCreate(['id' => $data['id']], $data);
        }
    }
}
