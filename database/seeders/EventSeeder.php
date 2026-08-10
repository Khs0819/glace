<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventImage;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        Event::query()->each(fn ($e) => $e->delete());

        // Data ported from fake-data/events.ts → FAKE_EVENTS
        $desc = 'كل عام وانتم بخير بحلول عيد الفطر المبارك احتفالنا معكم بالعيد أجمل . أهلا وسهلاُ بكم في جلاسيه فرع الاتصالات تفضلوا عنا , هناك عروض مميزة بانتظاركم';

        $events = [
            'مشارك معرض الصناعات الغذائية الفلسطينية',
            'أجواء العيد مع جلاسيه غير',
            'افتتاح فرع جديد فرع الأمن العام',
            'تقدم إدارة جلاسيه بالشكر و التقدير لكل فرد',
            'مشارك معرض الصناعات الغذائية الفلسطينية',
            'أجواء العيد مع جلاسيه غير',
            'افتتاح فرع جديد فرع الأمن العام',
            'تقدم إدارة جلاسيه بالشكر و التقدير لكل فرد',
            'افتتاح فرع جديد فرع الأمن العام',
            'تقدم إدارة جلاسيه بالشكر و التقدير لكل فرد',
        ];

        foreach ($events as $title) {
            $event = Event::create([
                'title'       => $title,
                'date'        => '11/06/2020',
                'description' => $desc,
                'list_image'  => null,
            ]);

            for ($i = 0; $i < 4; $i++) {
                EventImage::create([
                    'event_id'   => $event->id,
                    'image_url'  => null,
                    'sort_order' => $i,
                ]);
            }
        }
    }
}
