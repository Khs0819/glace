<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Shared catalog (product_id IS NULL) + per-product addons
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            // Product-scoped addons die with their product. Using nullOnDelete
            // here would silently promote them into the shared catalog
            // (product_id IS NULL) and duplicate GET /menu/addons — handoff 08 §أ-5.
            $table->foreignUuid('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('slug'); // exposed as "id" in API
            $table->string('label');
            $table->decimal('price', 8, 2);
            $table->boolean('available')->default(true);
            $table->string('type')->default('toggle'); // toggle|counter
            $table->unsignedSmallInteger('max_qty')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};
