<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the cashier screen needs to run a whole shift without leaving it.
 *
 *   driver_payouts           — the end-of-night transfer to a driver, with the
 *                              receipt that proves it. Settlements point at the
 *                              payout that cleared them, so a driver's balance
 *                              is simply the delivery fees with no payout yet.
 *   driver_settlements       — the delivery fee the driver earned on the order.
 *                              `delivered_at` becomes nullable: the row is now
 *                              written when the driver is chosen, before the
 *                              order has been received.
 *   change_refund_requests   — the receipt of the transfer sent to the customer.
 *   cashier_shifts.summary   — sales figures frozen at close, next to the cash
 *                              count, so the report does not shift afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Every step checks first, so a run that stopped part-way (MySQL cannot
        // roll back a schema change) can simply be run again.
        if (! Schema::hasTable('driver_payouts')) {
            Schema::create('driver_payouts', function (Blueprint $table) {
                $table->id();

                // Restrict, not cascade: deleting a driver must never delete the
                // record of money paid to them. Drivers are switched off instead.
                $table->foreignId('driver_id')->constrained();
                $table->decimal('amount', 10, 2);
                $table->string('receipt')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('paid_at');
                $table->timestamps();

                $table->index(['driver_id', 'paid_at']);
            });
        }

        if (! Schema::hasColumn('driver_settlements', 'delivery_fee')) {
            Schema::table('driver_settlements', function (Blueprint $table) {
                $table->decimal('delivery_fee', 10, 2)->default(0)->after('order_total');
            });
        }

        if (! Schema::hasColumn('driver_settlements', 'payout_id')) {
            Schema::table('driver_settlements', function (Blueprint $table) {
                $table->foreignId('payout_id')->nullable()->after('shift_id')
                    ->constrained('driver_payouts')->nullOnDelete();
            });
        }

        // Idempotent by nature: making a column nullable twice changes nothing.
        Schema::table('driver_settlements', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->change();
        });

        if (! Schema::hasColumn('change_refund_requests', 'transfer_receipt')) {
            Schema::table('change_refund_requests', function (Blueprint $table) {
                $table->string('transfer_receipt')->nullable()->after('notes');
            });
        }

        if (! Schema::hasColumn('cashier_shifts', 'summary')) {
            Schema::table('cashier_shifts', function (Blueprint $table) {
                $table->json('summary')->nullable()->after('totals');
            });
        }
    }

    public function down(): void
    {
        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->dropColumn('summary');
        });

        Schema::table('change_refund_requests', function (Blueprint $table) {
            $table->dropColumn('transfer_receipt');
        });

        Schema::table('driver_settlements', function (Blueprint $table) {
            $table->dropForeign(['payout_id']);
            $table->dropColumn(['delivery_fee', 'payout_id']);
        });

        Schema::dropIfExists('driver_payouts');
    }
};
