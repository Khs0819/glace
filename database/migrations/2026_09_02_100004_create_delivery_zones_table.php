<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Replaces the frontend's hardcoded src/lib/deliveryZones.ts. The slug is
        // the primary key because it is what the frontend already stores against
        // saved addresses ("rimal", "shejaiya", …) — a numeric id would orphan
        // every address the moment this table is reseeded.
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->string('id', 60)->primary();
            $table->string('name');
            $table->string('description')->nullable();

            // Shekels. Nothing derives it — the shop sets it per zone.
            $table->decimal('fee', 8, 2)->default(0);

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('available')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
