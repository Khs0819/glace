<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-product ice cream addon prices (brad-boza only: additive per-family price for ice-cream step)
        Schema::create('ice_cream_addon_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->string('flavor_family'); // classic|special|mix
            $table->decimal('price', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ice_cream_addon_prices');
    }
};
