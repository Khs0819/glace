<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('price', 8, 2);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->boolean('available')->default(true);
            $table->boolean('is_premium_mix_flavor')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_items');
    }
};
