<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `payment_accounts.account_number`, for databases that never got it.
 *
 * The column was added to 2026_09_13_200001 after that migration had already
 * run on production. Laravel never re-runs a migration it has recorded, so the
 * column was never created there, and every save of a payment account failed
 * with "Unknown column 'account_number'". A schema change always goes in a new
 * migration; this is that migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payment_accounts', 'account_number')) {
            return;
        }

        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->string('account_number')->nullable()->after('bank_name');
        });
    }

    public function down(): void
    {
        // Left in place: 2026_09_13_200001 also creates it on a fresh database,
        // and rolling this back there would drop a column that one owns.
    }
};
