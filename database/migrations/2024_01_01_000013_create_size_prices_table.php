<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('size_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('size_id')->constrained('product_sizes')->cascadeOnDelete();
            $table->string('flavor_family'); // classic|special|mix
            $table->decimal('price', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('size_prices');
    }
};
