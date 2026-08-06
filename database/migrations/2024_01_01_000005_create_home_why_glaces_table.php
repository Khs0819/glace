<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row CMS table for the home "Why Glace" section
        Schema::create('home_why_glaces', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->json('features'); // [{ label, image }, ...]
            $table->string('video_url');
            $table->string('video_thumbnail');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_why_glaces');
    }
};
