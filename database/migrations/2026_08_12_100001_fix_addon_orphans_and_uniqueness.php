<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Handoff 08 §أ-5 / §ب-5 — duplicate ids in GET /menu/addons.
 *
 * Root cause: addons.product_id used nullOnDelete(), so deleting a product
 * (or re-running ProductSeeder, which deletes every product first) turned its
 * product-scoped addons into product_id = NULL rows — i.e. shared-catalog
 * entries. Re-seeding then recreated the product-scoped copies, leaving one
 * orphan + one live row per slug (the reported 23 rows / 15 unique ids).
 *
 * This migration removes the orphans, collapses exact duplicates, and switches
 * the foreign key to cascadeOnDelete so it cannot happen again.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop orphans: a shared addon (product_id IS NULL) whose slug also
        //    exists as a product-scoped addon was never a real shared addon.
        $productScoped = DB::table('addons')->whereNotNull('product_id')->pluck('slug')->unique();

        if ($productScoped->isNotEmpty()) {
            DB::table('addons')
                ->whereNull('product_id')
                ->whereIn('slug', $productScoped)
                ->delete();
        }

        // 2. Collapse any remaining exact duplicates, keeping the lowest id.
        $duplicates = DB::table('addons')
            ->select('product_id', 'slug', DB::raw('MIN(id) as keep_id'))
            ->groupBy('product_id', 'slug')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            DB::table('addons')
                ->where('slug', $row->slug)
                ->where('id', '!=', $row->keep_id)
                ->when(
                    $row->product_id === null,
                    fn ($q) => $q->whereNull('product_id'),
                    fn ($q) => $q->where('product_id', $row->product_id),
                )
                ->delete();
        }

        // 3. Repoint the foreign key: a product's addons die with the product.
        //    SQLite cannot ALTER a foreign key; fresh databases already get the
        //    correct definition from the create_addons_table migration.
        if (DB::getDriverName() === 'mysql') {
            Schema::table('addons', function (Blueprint $table) {
                $table->dropForeign(['product_id']);
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            });
        }

        // 4. Guarantee slug uniqueness per owner at the database level.
        if (! $this->hasIndex('addons', 'addons_product_id_slug_unique')) {
            Schema::table('addons', function (Blueprint $table) {
                $table->unique(['product_id', 'slug']);
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('addons', 'addons_product_id_slug_unique')) {
            Schema::table('addons', function (Blueprint $table) {
                $table->dropUnique('addons_product_id_slug_unique');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            Schema::table('addons', function (Blueprint $table) {
                $table->dropForeign(['product_id']);
                $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            });
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn ($i) => $i['name'] === $index);
    }
};
