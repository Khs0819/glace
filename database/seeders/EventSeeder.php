<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventImage;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        // Event::truncate();
        // EventImage::truncate();

        $events = [
            [
                'title'       => 'مشارك معرض الصناعات الغذائية الفلسطينية',
                'date'        => '15/03/2023',
                'description' => 'شارك جلاسيه الأمير في معرض الصناعات الغذائية الفلسطينية الذي أقيم في مدينة غزة، وعرض أحدث منتجاته من الآيس كريم والحلويات الفاخرة.',
                'list_image'  => 'https://cdn.example.com/events/1-list.png',
                'images'      => [
                    'https://cdn.example.com/events/1-a.png',
                    'https://cdn.example.com/events/1-b.png',
                    'https://cdn.example.com/events/1-c.png',
                ],
            ],
            [
                'title'       => 'أجواء العيد مع جلاسيه الأمير',
                'date'        => '11/06/2020',
                'description' => 'كل عام وانتم بخير بحلول عيد الفطر المبارك احتفالنا معكم بالعيد أجمل مع مجموعة خاصة من نكهات الآيس كريم والحلويات المميزة.',
                'list_image'  => 'https://cdn.example.com/events/2-list.png',
                'images'      => [
                    'https://cdn.example.com/events/2-a.png',
                    'https://cdn.example.com/events/2-b.png',
                    'https://cdn.example.com/events/2-c.png',
                    'https://cdn.example.com/events/2-d.png',
                ],
            ],
            [
                'title'       => 'افتتاح فرع تل الهوا',
                'date'        => '01/09/2022',
                'description' => 'يسعدنا الإعلان عن افتتاح فرعنا الجديد في منطقة تل الهوا، نقدم لكم نفس الجودة والنكهات الرائعة في موقع أقرب إليكم.',
                'list_image'  => 'https://cdn.example.com/events/3-list.png',
                'images'      => [
                    'https://cdn.example.com/events/3-a.png',
                    'https://cdn.example.com/events/3-b.png',
                ],
            ],
        ];

        foreach ($events as $eventData) {
            $images = $eventData['images'];
            unset($eventData['images']);

            $event = Event::create($eventData);

            foreach ($images as $i => $url) {
                EventImage::create([
                    'event_id'   => $event->id,
                    'image_url'  => $url,
                    'sort_order' => $i,
                ]);
            }
        }
    }
}
