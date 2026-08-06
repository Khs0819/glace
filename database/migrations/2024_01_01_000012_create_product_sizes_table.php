<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            // container_slug (nullable) restricts this size to one container (references product_containers.slug within same product)
            $table->string('container_slug')->nullable();
            $table->string('slug'); // exposed as "id" in API (e.g. "cup-small")
            $table->string('label');
            $table->unsignedSmallInteger('max_balls')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sizes');
    }
};
