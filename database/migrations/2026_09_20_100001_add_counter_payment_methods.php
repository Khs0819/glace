<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A row — and therefore a switch — for the methods taken at the counter.
 *
 * Only the three transfer destinations were ever seeded, so cash, card and
 * automatic Jawwal Pay had nothing to switch off in the dashboard: hiding them
 * from the storefront was not possible. Each now has its own row.
 *
 * Cash and card go in switched on, because that is what the shop takes today.
 * Automatic Jawwal Pay goes in switched off: it charges a real wallet through
 * the gateway, and the shop turns it on once `jawwalpay:check` passes against
 * production.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('payment_accounts')->pluck('method')->all();

        $rows = [
            ['method' => 'cash',   'label' => 'نقداً داخل المحل',       'active' => true,  'sort' => 10],
            ['method' => 'visa',   'label' => 'بطاقة داخل المحل',       'active' => true,  'sort' => 11],
            ['method' => 'jawwal', 'label' => 'جوال باي (دفع مباشر)',   'active' => false, 'sort' => 12],
        ];

        foreach ($rows as $row) {
            if (in_array($row['method'], $existing, true)) {
                continue;
            }

            DB::table('payment_accounts')->insert([
                'method'        => $row['method'],
                'holder_name'   => (string) config('storefront.shop.name', 'جلاسيه الأمير'),
                'primary_label' => $row['label'],
                'active'        => $row['active'],
                'sort_order'    => $row['sort'],
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Left in place: these are switches the shop may since have used, and
        // dropping them would silently re-enable whatever was turned off.
    }
};
