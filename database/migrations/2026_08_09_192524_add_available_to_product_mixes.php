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
        Schema::table('product_mixes', function (Blueprint $table) {
            $table->boolean('available')->default(true)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('product_mixes', function (Blueprint $table) {
            $table->dropColumn('available');
        });
    }
};