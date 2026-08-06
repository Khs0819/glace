<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flavors', function (Blueprint $table) {
            $table->string('id')->primary(); // slug e.g. "pistachio"
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('image');
            $table->string('family'); // classic|special|stevia
            $table->boolean('available')->default(true);
            $table->boolean('is_premium_mix_flavor')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flavors');
    }
};
