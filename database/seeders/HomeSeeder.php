<?php

namespace Database\Seeders;

use App\Models\HeroSlide;
use App\Models\HomeAbout;
use App\Models\HomeWhyGlace;
use App\Models\Branch;
use Illuminate\Database\Seeder;

class HomeSeeder extends Seeder
{
    public function run(): void
    {
        // Hero Slides
        // HeroSlide::truncate();
        HeroSlide::insert([
            [
                'man_img'         => 'https://cdn.example.com/home/hero/man-1.png',
                'piece_img'       => 'https://cdn.example.com/home/hero/piece-1.png',
                'zigzags_img'     => 'https://cdn.example.com/home/hero/zigzags.png',
                'title_h1'        => 'جلاسية الأمير',
                'title_h2'        => 'لإنتاج الآيس كريم و البراد و العصائر و الحلويات',
                'bg_color'        => '#51C9F4',
                'header_bg_color' => '#51c9f4',
                'h1_bg_color'     => '#53352a',
                'h2_bg_color'     => '#51c9f4',
                'sort_order'      => 1,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'man_img'         => 'https://cdn.example.com/home/hero/man-2.png',
                'piece_img'       => 'https://cdn.example.com/home/hero/piece-2.png',
                'zigzags_img'     => 'https://cdn.example.com/home/hero/zigzags.png',
                'title_h1'        => 'أجود أنواع الآيس كريم',
                'title_h2'        => 'تجربة لا تُنسى من مذاق الحلويات الطازجة',
                'bg_color'        => '#F4A851',
                'header_bg_color' => '#f4a851',
                'h1_bg_color'     => '#53352a',
                'h2_bg_color'     => '#f4a851',
                'sort_order'      => 2,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
        ]);

        // About Section
        HomeAbout::truncate();
        HomeAbout::create([
            'title'      => 'موهوبون في صناعة الأيسكريم !',
            'paragraphs' => [
                'تأسس جلاسيه الأمير عام 2015 كأحد أفرع شركة أسكمو الأمير للإنتاج الغذائي، وقد نشأ الفرع ليكون متخصصاً في إنتاج وبيع الآيس كريم.',
                'تعمل الشركة على تقديم أجود أنواع الآيس كريم والبراد والعصائر الطبيعية والحلويات بأعلى معايير الجودة والنظافة.',
            ],
            'image'     => 'https://cdn.example.com/home/about/character.png',
            'cta_label' => 'اعرف أكثر',
            'cta_href'  => '/about',
        ]);

        // Why Glace Section
        HomeWhyGlace::truncate();
        HomeWhyGlace::create([
            'title'       => 'لماذا جلاسيه الأمير؟',
            'description' => 'جلاسيه الأمير حاصلة على شهادة الجودة العالمية لسلامة الغذاء ISO 22000',
            'features'    => [
                ['label' => 'جودة عالية',  'image' => 'https://cdn.example.com/home/why/feature-p.png'],
                ['label' => 'أمانة وثقة',  'image' => 'https://cdn.example.com/home/why/feature-b.png'],
                ['label' => 'نكهات متنوعة', 'image' => 'https://cdn.example.com/home/why/feature-g.png'],
            ],
            'video_url'       => 'https://www.youtube.com/embed/ShMr0XzIqSM',
            'video_thumbnail' => 'https://cdn.example.com/home/why/video-thumb.png',
        ]);

        // Branches
        Branch::truncate();
        Branch::insert([
            [
                'id'            => 'ramal',
                'label'         => 'فرع الرمال',
                'map_src'       => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3393.5!2d34.4667!3d31.5000',
                'address'       => 'غزة، الرمال، شارع الشهداء، بجانب البنك الإسلامي',
                'phone'         => '0592 226 522',
                'whatsapp'      => '0592 226 522',
                'weekday_hours' => 'PM 11:45 – AM 10:00',
                'friday_hours'  => 'PM 11:45 – PM02:00',
                'border_radius' => '32% 68% 69% 31% / 30% 28% 72% 70%',
                'sort_order'    => 1,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            [
                'id'            => 'tel-alhawa',
                'label'         => 'فرع تل الهوا',
                'map_src'       => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3393.5!2d34.4555!3d31.4900',
                'address'       => 'غزة، تل الهوا، الشارع الرئيسي',
                'phone'         => '0592 226 533',
                'whatsapp'      => '0592 226 533',
                'weekday_hours' => 'PM 11:45 – AM 10:00',
                'friday_hours'  => 'PM 11:45 – PM02:00',
                'border_radius' => '61% 39% 35% 65% / 61% 44% 56% 39%',
                'sort_order'    => 2,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
        ]);
    }
}