<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Driver delivery settlements.
 *
 * Automatically created when a delivery order reaches "تم الاستلام". Each row
 * records what the driver carried, whether cash was collected, and which shift
 * it belongs to — so the end-of-day reconciliation can answer "how much cash
 * should driver X be handing back".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained();
            $table->foreignUuid('order_id')->constrained();
            $table->foreignId('shift_id')->nullable()->constrained('cashier_shifts');
            $table->string('order_reference');
            $table->decimal('order_total', 10, 2);
            $table->string('payment_method');
            $table->boolean('cash_collected')->default(false);
            $table->timestamp('delivered_at');
            $table->timestamps();

            $table->index('driver_id');
            $table->index('shift_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_settlements');
    }
};
