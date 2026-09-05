<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            // UUID like products, so an order id can be handed to the customer
            // without leaking how many orders the shop has taken.
            $table->uuid('id')->primary();

            // Short, human-sayable handle for the phone and the receipt.
            $table->string('reference', 20)->unique();

            // Guest checkout has no login, so the only thing separating one
            // customer's order from another's is this. Returned once, at
            // creation, and required on every later call about the order.
            $table->string('public_token', 64)->unique();

            $table->string('customer_name');
            $table->string('customer_phone', 30);
            $table->text('notes')->nullable();

            // pending → awaiting_payment → paid | failed | cancelled
            $table->string('status')->default('pending')->index();

            // Priced from the catalog on the server. The client never supplies
            // an amount — it only says what it wants.
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('ILS');

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
