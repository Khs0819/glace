<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The delivery drivers the shop hands orders to.
     *
     * Until now a driver was a JSON blob typed into each order, which meant
     * the same person was re-typed on every delivery, spelled differently each
     * time, and could not be looked up, counted, or phoned from anywhere but
     * the one order that happened to name them.
     *
     * Availability is deliberately NOT a column. A flag would have to be set,
     * cleared, and repaired whenever anything went wrong, and it would be
     * wrong the first time somebody closed the tab mid-assignment. It is
     * derived instead: a driver is busy while an order of theirs is on the
     * road, which is a fact the orders table already holds.
     */
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('phone', 20);

            // Off rather than deleted: a driver who has left still has to stay
            // readable on the deliveries they made.
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['active', 'name']);
        });

        Schema::table('orders', function (Blueprint $table) {
            // The snapshot in `driver` stays: it is what the order looked like
            // that day, and a driver later renamed or deleted must not rewrite
            // history. This is the live link for everything else.
            $table->foreignId('driver_id')->nullable()->after('driver')
                ->constrained('drivers')->nullOnDelete();

            $table->index(['driver_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
            $table->dropIndex(['driver_id', 'status']);
            $table->dropColumn('driver_id');
        });

        Schema::dropIfExists('drivers');
    }
};
