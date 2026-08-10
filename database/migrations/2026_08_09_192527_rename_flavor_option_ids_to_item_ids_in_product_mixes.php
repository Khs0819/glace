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
            $table->renameColumn('flavor_option_ids', 'item_ids');
        });
    }

    public function down(): void
    {
        Schema::table('product_mixes', function (Blueprint $table) {
            $table->renameColumn('item_ids', 'flavor_option_ids');
        });
    }
};
