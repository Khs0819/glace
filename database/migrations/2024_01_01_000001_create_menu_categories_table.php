<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_categories', function (Blueprint $table) {
            $table->string('id')->primary(); // slug e.g. "pancake"
            $table->string('label');
            $table->string('icon'); // ice-cream|cup-soda|cake|glass-water|milk|apple
            $table->string('accent_color', 20);
            $table->string('gradient_from', 20);
            $table->string('gradient_to', 20);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_categories');
    }
};
