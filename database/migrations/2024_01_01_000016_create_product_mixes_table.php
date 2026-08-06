<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_mixes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->string('slug'); // exposed as "id" in API
            $table->string('label');
            $table->unsignedSmallInteger('pick'); // how many to pick
            $table->decimal('base_price', 8, 2);
            $table->decimal('flavor_price', 8, 2);
            $table->decimal('premium_flavor_price', 8, 2);
            $table->json('flavor_option_ids'); // array of item labels or flavor slugs
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_mixes');
    }
};
