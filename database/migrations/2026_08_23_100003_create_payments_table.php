<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();

            $table->string('provider')->default('jawwalpay');
            $table->string('method')->default('otp'); // otp (MFP) — qr is not exposed

            // Every Service Bus call carries its own msgId, and reusing one is
            // rejected with code 46 — so the code request and the charge each
            // get their own. Both are kept: they are the only handle we have
            // when reconciling against the provider's search_trans output.
            $table->string('otp_msg_id', 32)->nullable()->unique();
            $table->string('charge_msg_id', 32)->nullable()->unique();

            $table->string('wallet', 20);   // normalised 00970XXXXXXXXX
            $table->decimal('amount', 10, 2);

            // initiated → otp_sent → paid | failed | unresolved | expired
            $table->string('status')->default('initiated')->index();

            // Their answer, kept whether it was yes or no: errorCd is what
            // support will ask for, ref is the only handle they can trace.
            $table->string('error_code', 8)->nullable();
            $table->string('error_description')->nullable();
            $table->string('provider_reference')->nullable()->index();

            $table->unsignedTinyInteger('confirm_attempts')->default(0);
            $table->timestamp('otp_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();

            // Last envelope, minus anything that could be replayed.
            $table->json('last_response')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
