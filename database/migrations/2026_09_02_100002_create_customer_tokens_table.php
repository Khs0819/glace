<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Only the hash is kept. A dump of this table must not be enough to
            // impersonate anybody — same reasoning as a password column.
            $table->string('token_hash', 64)->unique();

            // Handy for the dashboard when a customer reports a session they do
            // not recognise; never used for authorisation.
            $table->string('device')->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tokens');
    }
};
