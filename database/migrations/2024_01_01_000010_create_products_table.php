<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('category_id'); // FK to menu_categories.id (slug)
            $table->foreign('category_id')->references('id')->on('menu_categories');
            $table->string('kind'); // builder|flat-list
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('available')->default(true);
            $table->boolean('has_addons')->default(false); // legacy flag
            $table->boolean('has_notes')->default(false);
            $table->boolean('has_favorites')->default(false);
            $table->boolean('has_image_zoom')->default(false);
            $table->boolean('in_store_only')->default(false);

            // builder-only fields
            $table->string('selection_mode')->nullable(); // repeatable|toggle
            $table->string('pricing_label')->nullable();
            $table->boolean('has_extra_biscuit_addon')->default(false);
            $table->boolean('includes_ice_cream_step')->default(false);
            $table->json('flavor_families')->nullable(); // ["classic","special","mix"]

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
