<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('man_img')->nullable();
            $table->string('piece_img')->nullable();
            $table->string('zigzags_img')->nullable();
            $table->string('title_h1');
            $table->string('title_h2');
            $table->string('bg_color', 20);
            $table->string('header_bg_color', 20);
            $table->string('h1_bg_color', 20);
            $table->string('h2_bg_color', 20);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};