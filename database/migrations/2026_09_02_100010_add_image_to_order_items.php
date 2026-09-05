<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Snapshotted for the same reason as the name and the price: the
            // storefront shows a thumbnail next to every line in order history,
            // and that must not go blank when the product is deleted or its
            // photo is replaced (handoff 12 — items[].image).
            $table->string('image')->nullable()->after('product_name');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
