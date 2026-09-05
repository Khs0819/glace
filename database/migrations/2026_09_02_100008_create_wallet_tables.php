<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();

            // The running balance, kept alongside the ledger rather than summed
            // on every read. Both are written in one transaction and a row lock
            // is taken before either changes, so they cannot disagree.
            $table->decimal('balance', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 10, 2);
            $table->string('type', 10);   // credit|debit
            $table->string('label');
            $table->string('method', 20)->nullable();
            $table->string('receipt_image')->nullable();

            // What moved the money, when it was an order. Soft pointer: deleting
            // an order must not rewrite the customer's statement.
            $table->foreignUuid('order_id')->nullable()->constrained()->nullOnDelete();

            // Balance immediately after this row was applied — an audit trail
            // that survives even if a later row is corrected.
            $table->decimal('balance_after', 10, 2);

            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
        });

        Schema::create('topup_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 10, 2);
            $table->string('method', 20); // bop|paypal|jawwal|jawwal-manual

            // قيد المراجعة | مكتمل | مرفوض. Never anything else: a request that
            // has not been approved by a human has not added a shekel.
            $table->string('status', 20)->default('قيد المراجعة')->index();

            $table->string('receipt_image')->nullable();
            $table->text('receipt_note')->nullable();
            $table->string('phone', 15)->nullable();

            // Who approved it and when — this is the only path by which a
            // balance goes up, so it is worth being able to answer "who".
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            // Set once, when approval creates the credit. Its presence is what
            // makes a second approval a no-op rather than free money.
            $table->foreignUlid('transaction_id')->nullable()
                ->constrained('wallet_transactions')->nullOnDelete();

            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topup_requests');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
