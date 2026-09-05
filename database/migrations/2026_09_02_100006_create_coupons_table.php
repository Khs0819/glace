<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Replaces VALID_COUPONS in the frontend's cartStore.ts, where every code
        // in the shop was readable in the bundle.
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            // Compared case-insensitively by upper-casing on the way in, so
            // "glace10" and "GLACE10" are the same coupon rather than two.
            $table->string('code', 40)->unique();

            $table->string('type', 10)->default('fixed'); // fixed|percent
            $table->decimal('value', 8, 2);

            // percent only — stops "50% off" quietly becoming unbounded on a
            // large order.
            $table->decimal('max_discount', 8, 2)->nullable();

            $table->decimal('min_subtotal', 8, 2)->nullable();
            $table->timestamp('expires_at')->nullable();

            // Total redemptions allowed across all customers, and per customer.
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);

            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
