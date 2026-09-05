<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();

            // The catalog keeps moving after an order is placed — items get
            // renamed, repriced, deleted. So the product is only a soft
            // pointer and everything the receipt needs is snapshotted here.
            $table->foreignUuid('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_slug');
            $table->string('product_name');
            $table->string('kind'); // builder|flat-list, as it was when ordered

            // What the customer picked, exactly as the pricer resolved it:
            // container/size/flavors, or item/mix, plus per-unit addons.
            $table->json('selection');

            // Arabic one-liner for the dashboard and the kitchen ticket, built
            // from the labels that were live at order time.
            $table->text('description');

            // Units in one line can carry different addons, so the addon
            // charge is a line-level sum rather than something that folds into
            // a single unit price:  line_total = unit_price × quantity + addons_total
            $table->decimal('unit_price', 10, 2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('addons_total', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
