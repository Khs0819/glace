<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The name customers see for a payment method, set from the dashboard.
 *
 * Empty means "use the storefront's own label", so nothing changes on screen
 * until somebody writes one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payment_accounts', 'display_name')) {
            return;
        }

        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->string('display_name', 120)->nullable()->after('method');
        });
    }

    public function down(): void
    {
        Schema::table('payment_accounts', fn (Blueprint $table) => $table->dropColumn('display_name'));
    }
};
