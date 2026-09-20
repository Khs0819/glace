<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clear the trading history so the shop opens on real figures.
 *
 * Everything written while the system was being tried out — orders, receipts,
 * payments, shifts, driver balances, wallets and their top-ups — goes. The
 * shop itself stays: customers and their addresses, the menu, drivers, payment
 * accounts, opening hours, coupons and staff logins.
 *
 * This cannot be undone, which is why it asks first and prints exactly what it
 * is about to remove. Take a database backup before running it.
 */
class LaunchReset extends Command
{
    protected $signature = 'launch:reset {--force : Skip the confirmation prompt}';

    protected $description = 'Zero the wallets and clear every order, top-up and shift before going live';

    /**
     * Deleted in this order: a child always before what it points at, so the
     * foreign keys never have to be switched off.
     *
     * @var array<string, string>
     */
    private const TABLES = [
        'payments'               => 'محاولات الدفع عبر جوال باي',
        'change_refund_requests' => 'طلبات استرداد الباقي',
        'driver_settlements'     => 'تسويات السائقين',
        'driver_payouts'         => 'دفعات السائقين',
        'order_items'            => 'أصناف الطلبات',
        'orders'                 => 'الطلبات',
        'cashier_shifts'         => 'ورديات الكاشير',
        'topup_requests'         => 'طلبات شحن المحفظة',
        'wallet_transactions'    => 'حركات المحافظ',
        'wallets'                => 'أرصدة المحافظ',
        'otp_codes'              => 'رموز التحقق المؤقتة',
    ];

    public function handle(): int
    {
        $counts = $this->counts();
        $total  = array_sum($counts);

        $this->newLine();
        $this->components->info('تصفير بيانات التجربة قبل الإطلاق');
        $this->table(
            ['الجدول', 'ما سيُحذف'],
            array_map(
                fn (string $table) => [self::TABLES[$table] . "  ({$table})", $counts[$table]],
                array_keys($counts),
            ),
        );

        $this->line('  يبقى كما هو: الزبائن وعناوينهم، المنيو، السائقون، حسابات الدفع، مواعيد العمل، الكوبونات، ومستخدمو اللوحة.');
        $this->line('  رصيد كل محفظة يعود إلى صفر، وصفحتا الطلبات وشحن الرصيد تصبحان فارغتين.');
        $this->line("  المجموع: {$total} سجلاً ستُحذف نهائياً.");
        $this->newLine();

        if ($total === 0) {
            $this->components->info('لا يوجد شيء لحذفه — النظام نظيف بالفعل.');

            return self::SUCCESS;
        }

        // Irreversible, so the default answer is no and --force has to be
        // deliberate. A production database should be backed up first.
        if (! $this->option('force') && ! $this->confirm('هل أخذت نسخة احتياطية من قاعدة البيانات وتريد المتابعة؟', false)) {
            $this->components->warn('أُلغي التصفير — لم يُحذف شيء.');

            return self::FAILURE;
        }

        DB::transaction(function () {
            foreach (array_keys(self::TABLES) as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }

            // Coupon counters are part of the trading history too: the orders
            // that consumed them are gone, so the count has to go with them.
            if (Schema::hasTable('coupons')) {
                DB::table('coupons')->update(['used_count' => 0]);
            }
        });

        $this->newLine();
        $this->components->info('تم التصفير. كل المحافظ على صفر، ولا توجد طلبات أو طلبات شحن.');
        $this->line('  الخطوة التالية: افتح وردية جديدة من شاشة الكاشير.');

        return self::SUCCESS;
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $counts = [];

        foreach (array_keys(self::TABLES) as $table) {
            $counts[$table] = Schema::hasTable($table) ? DB::table($table)->count() : 0;
        }

        return $counts;
    }
}
