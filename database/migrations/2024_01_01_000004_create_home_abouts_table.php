<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row CMS table for the home "About" section
        Schema::create('home_abouts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->json('paragraphs'); // array of strings
            $table->string('image');
            $table->string('cta_label');
            $table->string('cta_href');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_abouts');
    }
};
