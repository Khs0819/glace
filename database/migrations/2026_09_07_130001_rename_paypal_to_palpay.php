<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `paypal` was never PayPal.
     *
     * PalPay is a Palestinian payment company; the American one of a similar
     * name is not accepted here at all. The mistake was not cosmetic — the
     * storefront showed customers "PayPal" next to a Palestinian account
     * number, which is an instruction to pay through the wrong service.
     *
     * The rows have to move with the code: a stored `paypal` against a
     * PAYMENT_METHODS list that no longer contains it is an order nothing can
     * label, price a refund for, or count in a report.
     */
    private const TARGETS = [
        'orders'          => 'payment_method',
        'payments'        => 'method',
        'topup_requests'  => 'method',
        'payment_accounts' => 'method',
        'wallet_transactions' => 'method',
    ];

    public function up(): void
    {
        $this->rename('paypal', 'palpay');

        // Counter methods (cash, card, automatic Jawwal Pay) have no account
        // to transfer into, so the two transfer columns cannot stay NOT NULL —
        // saving such a row would fail at the database with an error about a
        // field the form deliberately does not show.
        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->string('primary_label')->nullable()->change();
            $table->string('primary_value')->nullable()->change();
        });
    }

    public function down(): void
    {
        $this->rename('palpay', 'paypal');

        // Reversible only while no counter-method row exists; one that does
        // would have nothing to put in these columns.
        DB::table('payment_accounts')->whereNull('primary_label')->update(['primary_label' => '']);
        DB::table('payment_accounts')->whereNull('primary_value')->update(['primary_value' => '']);

        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->string('primary_label')->nullable(false)->change();
            $table->string('primary_value')->nullable(false)->change();
        });
    }

    private function rename(string $from, string $to): void
    {
        foreach (self::TARGETS as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)->where($column, $from)->update([$column => $to]);
        }
    }
};
