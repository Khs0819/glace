<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The note for whoever carries the order: gate code, floor, a landmark.
 *
 * Kept apart from `notes`, which holds the customer's note about the order
 * itself ("بدون مكسرات"). The two go to different people — the kitchen reads
 * one, the driver the other — so merging them into one text would hand each
 * person something they have to read around.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Safe to run again after a partial run (MySQL cannot roll back DDL).
        if (Schema::hasColumn('orders', 'captain_note')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->text('captain_note')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('captain_note');
        });
    }
};
