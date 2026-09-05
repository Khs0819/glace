<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();

            // Not a customer_id: the whole point is that the first code for a
            // number goes out before any account exists.
            $table->string('phone', 15)->index();

            // What the code is for. Login and an order's payment confirmation
            // must never satisfy each other.
            $table->string('purpose', 30)->default('login')->index();

            // Hashed exactly like a password — handoff 08 asks for this by name.
            $table->string('code_hash');

            // Wrong guesses are capped, otherwise six digits is a short walk.
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();

            // Carried through verification for flows that need it (a Jawwal Pay
            // charge remembers the amount the code was issued against).
            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index(['phone', 'purpose', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
