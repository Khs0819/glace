<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->string('id')->primary(); // slug e.g. "ramal"
            $table->string('label');
            $table->text('map_src');
            $table->text('address');
            $table->string('phone', 30);
            $table->string('whatsapp', 30);
            $table->string('weekday_hours', 60);
            $table->string('friday_hours', 60);
            $table->string('border_radius', 100); // CSS border-radius value
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
