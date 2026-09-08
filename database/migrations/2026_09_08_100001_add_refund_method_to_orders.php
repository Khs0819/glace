<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a refund actually went.
     *
     * `refunded_amount` alone cannot answer the one question the books need:
     * a cash order refunded as store credit takes nothing out of the drawer,
     * while the same amount handed back in notes does. Without this column the
     * drawer reconciliation subtracts both, and every wallet refund makes an
     * honest till look like it is holding a surplus nobody can explain.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // 'wallet' | 'cash'. Null on rows refunded before this existed.
            $table->string('refund_method', 20)->nullable()->after('refunded_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('refund_method');
        });
    }
};
