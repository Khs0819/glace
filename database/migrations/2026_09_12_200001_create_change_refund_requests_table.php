<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash change refund requests.
 *
 * When a customer pays cash and the total is less than what they handed over,
 * the cashier records a refund request for the difference. The request is
 * reviewed later — typically at shift close — and the money is transferred
 * to the customer through their chosen method.
 *
 * This is NOT the same as an order refund (OrderRefundService): that returns
 * the whole order total. This returns only the change that could not be given
 * in notes at the counter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_refund_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->string('order_reference');
            $table->decimal('amount', 10, 2);
            $table->string('holder_name');
            $table->string('holder_phone');
            $table->string('refund_method');       // jawwal, bop, palpay, cash
            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); // pending, completed, rejected
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('order_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_refund_requests');
    }
};
