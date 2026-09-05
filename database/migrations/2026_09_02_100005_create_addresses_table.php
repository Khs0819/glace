<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            // "addr_01J..." — the frontend already treats the id as an opaque
            // string, and a non-sequential one stops address ids being probed.
            $table->string('id', 40)->primary();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('type', 10)->default('home'); // home|work|other
            $table->string('label');
            $table->string('name');
            $table->string('phone', 15);
            $table->string('city')->default('غزة');

            // Kept even if the zone is later deleted from the dashboard: the
            // address still shows the customer what they typed, and an order
            // snapshots the zone name anyway.
            $table->string('zone_id', 60)->nullable();
            $table->foreign('zone_id')->references('id')->on('delivery_zones')->nullOnDelete();

            $table->string('street');
            $table->string('landmark')->nullable();

            // Optional GPS pin. decimal, not float — a drifting last digit here
            // moves the pin down the street.
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['customer_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
