<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A scoop of ice cream on a waffle, a crepe, a pancake.
 *
 * It is an addon on the product like any other — priced, switched on and off,
 * and validated by the same code — with one difference: it belongs to a
 * family the customer picks from ("كلاسيك" or "سبيشال"), and the storefront
 * draws those two lists beside the flavours instead of in the addon row.
 * Null means an ordinary addon, which is every row that exists today.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('addons', 'scoop_family')) {
            return;
        }

        Schema::table('addons', function (Blueprint $table) {
            $table->string('scoop_family', 20)->nullable()->after('type');
            $table->index(['product_id', 'scoop_family']);
        });
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'scoop_family']);
            $table->dropColumn('scoop_family');
        });
    }
};
