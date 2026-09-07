<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Over-payment at the counter, credited to the wallet as change.
     *
     * The customer declares at checkout what note they intend to hand over —
     * 100 for a 36 order — and the remainder becomes store credit instead of
     * coins. Two columns rather than one computed value, because they answer
     * different questions and drift apart on purpose:
     *
     *   `tendered_amount`  what the customer said they would pay. An intention.
     *   `change_credited`  what actually reached the wallet, and when the
     *                      cashier took the cash. Stays 0 until then.
     *
     * The gap between them is the whole audit trail: an order with a tendered
     * amount and no credit is one where the customer never turned up, and it
     * must not read as money owed.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('tendered_amount', 10, 2)->nullable()->after('total');
            $table->decimal('change_credited', 10, 2)->default(0)->after('tendered_amount');
            $table->timestamp('change_credited_at')->nullable()->after('change_credited');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['tendered_amount', 'change_credited', 'change_credited_at']);
        });
    }
};
