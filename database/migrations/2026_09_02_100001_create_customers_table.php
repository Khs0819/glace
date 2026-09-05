<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Storefront customers are deliberately NOT `users`. That table backs the
        // Filament panel, whose canAccessPanel() lets every row in — putting an
        // OTP-verified phone number there would hand the dashboard to anyone who
        // can receive an SMS. Different audience, different table, different guard.
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            // The identity is the phone; e-mail is optional for the whole life of
            // the account (handoff 09: "المعرّف الأساسي هو رقم الهاتف، مش الإيميل").
            // Unique but nullable, so any number of accounts may have none.
            $table->string('email')->nullable()->unique();

            // Stored normalised to 05XXXXXXXX so the same person cannot end up
            // with two accounts by typing +970 one day and 05 the next.
            $table->string('phone', 15)->unique();

            $table->boolean('blocked')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
