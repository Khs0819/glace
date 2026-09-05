<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use Illuminate\Database\Seeder;

/**
 * Delivery areas, replacing the frontend's src/lib/deliveryZones.ts (handoff 10).
 *
 * ⚠ The handoff names 31 zones but only prints two of them — `rimal` (10 ₪) and
 * `shejaiya` (15 ₪). Those two are reproduced exactly; the rest are the real
 * Gaza-Strip areas at the fee tiers the handoff names (0/10/15/20 ₪), and their
 * slugs are a best reading, not a quotation.
 *
 * Before launch, diff these ids against src/lib/deliveryZones.ts: a slug that
 * disagrees does not fail loudly — it silently orphans the `zoneId` on every
 * address the storefront has already saved. Fees are edited in the dashboard,
 * so only the ids really have to match.
 */
class DeliveryZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            // Gaza City — the two documented tiers.
            ['rimal',        'الرمال',            'حي الرمال',            10],
            ['shejaiya',     'الشجاعية',          null,                   15],
            ['gaza-center',  'وسط البلد',         'مركز مدينة غزة',       10],
            ['tuffah',       'التفاح',            null,                   15],
            ['daraj',        'الدرج',             null,                   15],
            ['zeitoun',      'الزيتون',           null,                   15],
            ['sabra',        'الصبرة',            null,                   10],
            ['tal-hawa',     'تل الهوا',          null,                   10],
            ['nasr',         'النصر',             null,                   10],
            ['sheikh-radwan', 'الشيخ رضوان',      null,                   10],
            ['sheikh-ijleen', 'الشيخ عجلين',      null,                   15],
            ['shati',        'الشاطئ',            'مخيم الشاطئ',          15],
            ['nasser',       'حي النصر',          null,                   10],
            ['jala',         'شارع الجلاء',       null,                    0],
            ['omar-mukhtar', 'عمر المختار',       null,                    0],

            // North Gaza.
            ['jabalia',      'جباليا',            null,                   20],
            ['jabalia-camp', 'مخيم جباليا',       null,                   20],
            ['beit-lahia',   'بيت لاهيا',         null,                   20],
            ['beit-hanoun',  'بيت حانون',         null,                   20],

            // Central.
            ['nuseirat',     'النصيرات',          null,                   20],
            ['bureij',       'البريج',            null,                   20],
            ['maghazi',      'المغازي',           null,                   20],
            ['deir-balah',   'دير البلح',         null,                   20],
            ['zawaida',      'الزوايدة',          null,                   20],
            ['musaddar',     'المصدر',            null,                   20],
            ['wadi-salqa',   'وادي السلقا',       null,                   20],

            // South.
            ['khan-younis',  'خان يونس',          null,                   20],
            ['bani-suheila', 'بني سهيلا',         null,                   20],
            ['abasan',       'عبسان',             null,                   20],
            ['rafah',        'رفح',               null,                   20],
            ['tal-sultan',   'تل السلطان',        null,                   20],
        ];

        foreach ($zones as $index => [$id, $name, $description, $fee]) {
            // updateOrCreate, not create: reseeding must not reset a fee the
            // shop has since changed in the dashboard for an existing zone.
            DeliveryZone::updateOrCreate(
                ['id' => $id],
                [
                    'name'        => $name,
                    'description' => $description,
                    'sort_order'  => $index,
                    'available'   => true,
                ] + (DeliveryZone::whereKey($id)->exists() ? [] : ['fee' => $fee]),
            );
        }
    }
}
