<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_containers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->string('slug'); // exposed as "id" in API (e.g. "cup","biscuit")
            $table->string('label');
            $table->boolean('available')->default(true);
            $table->string('name')->nullable(); // overrides product name
            $table->string('image')->nullable();
            $table->string('pricing_label')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_containers');
    }
};
