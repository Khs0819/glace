<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two additions from the first days of live trading.
 *
 *   delivery_zones.free_delivery — a zone the shop delivers to for nothing,
 *   switched on and off without losing the fee it normally charges.
 *
 *   change_refund_requests.kind — the same transfer-back request now carries
 *   two different things: the change from a cash payment ("change"), and the
 *   whole amount of a paid order the customer cancelled ("order"). They move
 *   different money and close differently, so the row says which it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('delivery_zones', 'free_delivery')) {
            Schema::table('delivery_zones', function (Blueprint $table) {
                $table->boolean('free_delivery')->default(false)->after('fee');
            });
        }

        if (! Schema::hasColumn('change_refund_requests', 'kind')) {
            Schema::table('change_refund_requests', function (Blueprint $table) {
                $table->string('kind', 20)->default('change')->after('order_reference');
            });
        }
    }

    public function down(): void
    {
        Schema::table('delivery_zones', fn (Blueprint $table) => $table->dropColumn('free_delivery'));
        Schema::table('change_refund_requests', fn (Blueprint $table) => $table->dropColumn('kind'));
    }
};
