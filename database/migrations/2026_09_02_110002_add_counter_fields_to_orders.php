<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the counter needs that the storefront never knew about: which table
     * an order belongs to, whether its receipt has been printed, and who took
     * the money.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Dine-in only. The storefront contract (handoff 12) has no table
            // field yet, so this is normally set by the cashier when the order
            // lands — but POST /orders accepts it too, ready for the day the
            // customer picks their own table in the app.
            $table->string('table_number', 20)->nullable()->after('delivery_method');

            // Printing. `print_count` separates "never printed" from "reprinted
            // because the first one jammed", which matters when a receipt is
            // the only paper record of a cash sale.
            $table->timestamp('printed_at')->nullable()->after('scheduled_for');
            $table->unsignedSmallInteger('print_count')->default(0)->after('printed_at');
            $table->text('print_error')->nullable()->after('print_count');

            // Who took the money, and during which shift. Both are what turn
            // "cash sales today" into a figure somebody is accountable for.
            $table->foreignId('paid_by')->nullable()->after('paid_at')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->after('paid_by')
                ->constrained('cashier_shifts')->nullOnDelete();

            // Amount actually refunded, which is not always the total — a
            // partial refund is a real thing and the accountant has to see it.
            $table->decimal('refunded_amount', 10, 2)->default(0)->after('total');
            $table->timestamp('refunded_at')->nullable()->after('refunded_amount');

            $table->index(['shift_id', 'payment_method']);
            $table->index(['delivery_method', 'status']);
            $table->index('printed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['paid_by']);
            $table->dropForeign(['shift_id']);
            $table->dropIndex(['shift_id', 'payment_method']);
            $table->dropIndex(['delivery_method', 'status']);
            $table->dropIndex(['printed_at']);

            $table->dropColumn([
                'table_number', 'printed_at', 'print_count', 'print_error',
                'paid_by', 'shift_id', 'refunded_amount', 'refunded_at',
            ]);
        });
    }
};
