<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which of the shop's accounts the customer actually transferred to.
     *
     * A method is not an account. The shop can hold two Jawwal Pay numbers in
     * two different names, and "paid by Jawwal Pay" does not say which of them
     * to go and check — so whoever verifies the receipt has been guessing, or
     * opening every account in turn.
     *
     * Nullable because it only means anything for the transfer methods, and
     * because every order written before today has no answer to give.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('payment_account_id')->nullable()->after('payment_method')
                ->constrained('payment_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['payment_account_id']);
            $table->dropColumn('payment_account_id');
        });
    }
};
