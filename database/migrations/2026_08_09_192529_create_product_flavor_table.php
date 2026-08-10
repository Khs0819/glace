<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_flavor', function (Blueprint $table) {
            $table->uuid('product_id');
            $table->string('flavor_id');
            $table->primary(['product_id', 'flavor_id']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('flavor_id')->references('id')->on('flavors')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_flavor');
    }
};
