<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The API exposes every product child's `slug` as its contract `id`
 * (IProductVariant.id, IMixRule.id, IContainerOption.id, ISizeOption.id).
 *
 * Swagger requires those ids to be "unique within the product" — mixes
 * reference items by id (IMixRule.itemIds) and sizes reference containers by
 * id (ISizeOption.containerId), so a duplicate silently breaks the ordering
 * flow. Nothing enforced that before; now the database does.
 */
return new class extends Migration
{
    private const TABLES = [
        'product_items',
        'product_mixes',
        'product_containers',
        'product_sizes',
    ];

    public function up(): void
    {
        // product_items.slug was added nullable; the API cannot emit a null id.
        $this->backfillItemSlugs();

        foreach (self::TABLES as $table) {
            $this->deduplicateSlugs($table);

            $index = "{$table}_product_id_slug_unique";
            if (! $this->hasIndex($table, $index)) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unique(['product_id', 'slug']);
                });
            }
        }

        Schema::table('product_items', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });

        // One price per flavor family, per size / per brad-boza product.
        foreach ([['size_prices', 'size_id'], ['ice_cream_addon_prices', 'product_id']] as [$table, $owner]) {
            $this->deduplicatePrices($table, $owner);

            $index = "{$table}_{$owner}_flavor_family_unique";
            if (! $this->hasIndex($table, $index)) {
                Schema::table($table, function (Blueprint $t) use ($owner) {
                    $t->unique([$owner, 'flavor_family']);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            $index = "{$table}_product_id_slug_unique";
            if ($this->hasIndex($table, $index)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropUnique($index));
            }
        }

        Schema::table('product_items', function (Blueprint $table) {
            $table->string('slug')->nullable()->change();
        });

        foreach ([['size_prices', 'size_id'], ['ice_cream_addon_prices', 'product_id']] as [$table, $owner]) {
            $index = "{$table}_{$owner}_flavor_family_unique";
            if ($this->hasIndex($table, $index)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropUnique($index));
            }
        }
    }

    private function backfillItemSlugs(): void
    {
        $rows = DB::table('product_items')
            ->where(fn ($q) => $q->whereNull('slug')->orWhere('slug', ''))
            ->get(['id', 'label']);

        foreach ($rows as $row) {
            $slug = Str::slug((string) $row->label) ?: 'item';
            DB::table('product_items')->where('id', $row->id)->update(['slug' => $slug . '-' . $row->id]);
        }
    }

    /** Suffix later rows so an existing duplicate cannot block the unique index. */
    private function deduplicateSlugs(string $table): void
    {
        $duplicates = DB::table($table)
            ->select('product_id', 'slug')
            ->groupBy('product_id', 'slug')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $ids = DB::table($table)
                ->where('product_id', $duplicate->product_id)
                ->where('slug', $duplicate->slug)
                ->orderBy('id')
                ->pluck('id')
                ->skip(1);

            foreach ($ids as $n => $id) {
                DB::table($table)->where('id', $id)->update([
                    'slug' => $duplicate->slug . '-' . ($n + 2),
                ]);
            }
        }
    }

    private function deduplicatePrices(string $table, string $owner): void
    {
        $duplicates = DB::table($table)
            ->select($owner, 'flavor_family', DB::raw('MIN(id) as keep_id'))
            ->groupBy($owner, 'flavor_family')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table($table)
                ->where($owner, $duplicate->{$owner})
                ->where('flavor_family', $duplicate->flavor_family)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn ($i) => $i['name'] === $index);
    }
};
