<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\HeroSlide;
use App\Models\HomeAbout;
use App\Models\HomeWhyGlace;
use Illuminate\Database\Seeder;

class HomeSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Hero Slides (6 slides from fake-data/homePage.ts) ────────────────
        HeroSlide::truncate();
        HeroSlide::insert([
            [
                'man_img'         => null,
                'piece_img'       => null,
                'zigzags_img'     => null,
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
                'man_img'         => null,
                'piece_img'       => null,
                'zigzags_img'     => null,
                'title_h1'        => 'رحلة لذيذة',
                'title_h2'        => 'عالم من النكهات المبهجة والمتعة العالية',
                'bg_color'        => '#3FBD59',
                'header_bg_color' => '#3FBD598a',
                'h1_bg_color'     => '#01580F',
                'h2_bg_color'     => '#01A41B',
                'sort_order'      => 2,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'man_img'         => null,
                'piece_img'       => null,
                'zigzags_img'     => null,
                'title_h1'        => 'يومك منعش',
                'title_h2'        => 'لا شيء يضاهي البهجة مع المثلجات في يوم حار',
                'bg_color'        => '#BEBB49',
                'header_bg_color' => '#BEBB498a',
                'h1_bg_color'     => '#F3900E',
                'h2_bg_color'     => '#F47251',
                'sort_order'      => 3,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'man_img'         => null,
                'piece_img'       => null,
                'zigzags_img'     => null,
                'title_h1'        => 'بوظة شهية',
                'title_h2'        => 'بوظة لذيذة، استمتع بالانتعاش في كل لقمة',
                'bg_color'        => '#DA51F4',
                'header_bg_color' => '#DA51F48a',
                'h1_bg_color'     => '#A506C4',
                'h2_bg_color'     => '#E883FB',
                'sort_order'      => 4,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'man_img'         => null,
                'piece_img'       => null,
                'zigzags_img'     => null,
                'title_h1'        => 'تذوق واستمتع',
                'title_h2'        => 'استمتع باللحظة مع بوظة جلاسيه الأمير',
                'bg_color'        => '#FF9900',
                'header_bg_color' => '#FF99008a',
                'h1_bg_color'     => '#005C5D',
                'h2_bg_color'     => '#F2A634',
                'sort_order'      => 5,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'man_img'         => null,
                'piece_img'       => null,
                'zigzags_img'     => null,
                'title_h1'        => 'كافئ نفسك',
                'title_h2'        => 'استرخ وتمتع بنكهات شهية ومنعشة',
                'bg_color'        => '#6C5950',
                'header_bg_color' => '#6C59508a',
                'h1_bg_color'     => '#A66A2E',
                'h2_bg_color'     => '#F0C648',
                'sort_order'      => 6,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
        ]);

        // ─── About Section ────────────────────────────────────────────────────
        HomeAbout::truncate();
        HomeAbout::create([
            'title'      => 'موهوبون في صناعة الأيسكريم !',
            'paragraphs' => [
                'تأسس جلاسيه الأمير عام 2015 كأحد أفرع شركة أسكمو الأمير التي تأسست على يد صاحبها السيد عماد الوادية عام 2000',
                'تعمل الشركة على تقديم أجود أنواع الآيس كريم والبراد بالإضافة للعصائر والحلويات، ولديها خبرة طويلة في هذا المجال حيث تسعى الشركة دوماً على التطوير و تحسين الأداء للوصول الى خدمة ومنتج يرقى بزبائننا الكرام',
            ],
            'image'     => null,
            'cta_label' => 'اعرف أكثر',
            'cta_href'  => '#',
        ]);

        // ─── Why Glace Section (6 features from fake-data) ───────────────────
        HomeWhyGlace::truncate();
        HomeWhyGlace::create([
            'title'       => 'لماذا جلاسيه الأمير؟',
            'description' => 'جلاسيه الأمير حاصلة على شهادة الجودة العالمية لسلامة الغذاء ISO 22000',
            'features'    => [
                ['label' => 'جودة عالية',    'image' => null],
                ['label' => 'أمانة وثقة',    'image' => null],
                ['label' => 'خدمة راقية',    'image' => null],
                ['label' => 'أسعار منافسة',  'image' => null],
                ['label' => 'خبرة عالية',    'image' => null],
                ['label' => 'سرعة ونظافة',   'image' => null],
            ],
            'video_url'       => 'https://www.youtube.com/embed/ShMr0XzIqSM',
            'video_thumbnail' => null,
        ]);

        // ─── Branches (3 branches with real map URLs from fake-data) ─────────
        Branch::truncate();
        Branch::insert([
            [
                'id'            => 'ramal',
                'label'         => 'فرع الرمال',
                'map_src'       => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3401.134069155089!2d34.442460474075105!3d31.52047749458951!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x14fd7fa668193343%3A0x63812dcdb0e703ee!2zZ2xhY2UgZWxhbWVlciDYrNmA2KfYs9mK2Ycg2KfZhNij2YXZitixINmB2LHYuSDYp9mG2LHYuSDYp9mA2LHZhdan2YQ!5e0!3m2!1sar!2s!4v1692262174549!5m2!1sar!2s',
                'address'       => 'غزة، الرمال، شارع الشهداء، غرب شركة الإتصالات بالجهة المقابلة للطابون، شرقي بنده مول',
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
                'id'            => 'nasr',
                'label'         => 'فرع النصر',
                'map_src'       => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d802.3957364051882!2d34.46570501521984!3d31.539686489519603!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x14fd7f5edd3f91d9%3A0xc83c9ac2a734d616!2zZ2xhY2UgZWxhbWVlciDYrNmA2KfYs9mK2Ycg2KfZhNij2YXZitixINmB2LHYuSDYp9mG2LYdtixg!5e0!3m2!1sar!2s!4v1692271440799!5m2!1sar!2s',
                'address'       => 'غزة، شارع النصر، مفترق الأمن العام، بجانب مكتبة عودة، بالقرب من الساب واي',
                'phone'         => '0592226577',
                'whatsapp'      => '00970592226577',
                'weekday_hours' => 'PM 11:45 – AM 10:00',
                'friday_hours'  => 'PM 11:45 – PM02:00',
                'border_radius' => '56% 44% 69% 31% / 70% 61% 39% 30%',
                'sort_order'    => 2,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            [
                'id'            => 'bahr',
                'label'         => 'فرع البحر',
                'map_src'       => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d13602.987559794487!2d34.46657431482985!3d31.531111026597035!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x14fd7ff1aaf9de67%3A0x7d0a7e3df1fa9c76!2z2KzZhNin2LPZitmHINin2YTYo9mF2YrYsSDZgdix2Lkg2KfZhNio2K3Ysigz2YTYoCBnbGFjZSBlbGFtZWVy!5e0!3m2!1sar!2s!4v1692271505959!5m2!1sar!2s',
                'address'       => 'غزة، كورنيش بحر غزة، دوار ال17، أول موقف لسيارات، منتجع السي سايد',
                'phone'         => '0592229892',
                'whatsapp'      => '00970592229892',
                'weekday_hours' => 'PM 11:45 – AM 10:00',
                'friday_hours'  => 'PM 11:45 – PM02:00',
                'border_radius' => '56% 44% 69% 31% / 53% 63% 37% 47%',
                'sort_order'    => 3,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
        ]);
    }
}

