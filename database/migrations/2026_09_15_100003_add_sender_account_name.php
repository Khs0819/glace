<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whose account a manual transfer came from.
 *
 * A receipt image proves a transfer happened; it does not reliably say from
 * whom, and a bank statement lists the sender's name. Recording that name next
 * to the order or top-up is what lets the person reviewing it match the money
 * that arrived to the request that claims it.
 *
 * Nullable because every existing row predates it, and because it only applies
 * to the manual transfer methods.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Each table checked on its own, so a run that stopped between the two
        // can be run again and finish.
        if (! Schema::hasColumn('orders', 'sender_account_name')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('sender_account_name', 190)->nullable()->after('receipt_note');
            });
        }

        if (! Schema::hasColumn('topup_requests', 'sender_account_name')) {
            Schema::table('topup_requests', function (Blueprint $table) {
                $table->string('sender_account_name', 190)->nullable()->after('receipt_note');
            });
        }
    }

    public function down(): void
    {
        Schema::table('topup_requests', function (Blueprint $table) {
            $table->dropColumn('sender_account_name');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('sender_account_name');
        });
    }
};
