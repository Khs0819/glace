<?php

use App\Models\Addon;
use App\Support\FlavorFamily;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One list of flavours, not two.
 *
 * «بوظة إضافية» kept flavour rows of its own — a name and a price typed by hand
 * on each product — beside the real list in القائمة ← النكهات. Two lists meant
 * two switches: the cashier closed pistachio the moment it ran out, and it went
 * on being offered on every crepe and waffle in the shop.
 *
 * A scoop row now points at a flavour. The price and the order stay on the row
 * because they belong to this product; the name, the family, and — the point of
 * all this — whether there is any left today come from the flavour itself.
 *
 * Nothing typed so far is thrown away. A row whose name matches a flavour is
 * linked to it; a row that matches nothing becomes a flavour. So the list the
 * shop already built survives the change, and afterwards it is edited in the
 * one place the rest of the menu is edited from.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('addons', 'flavor_id')) {
            Schema::table('addons', function (Blueprint $table) {
                // No foreign key: the suite runs on SQLite, which cannot add
                // one to a table that already exists. Addon::orderable()
                // carries the weight instead — a row pointing at a flavour that
                // is gone refuses to be sold rather than falling back to its
                // own stale copy of the name.
                $table->string('flavor_id')->nullable()->after('scoop_family');
                $table->index(['product_id', 'flavor_id']);
            });
        }

        $scoops = DB::table('addons')->whereNotNull('scoop_family')->whereNull('flavor_id')->get();

        foreach ($scoops as $scoop) {
            $label = trim((string) $scoop->label);

            if ($label === '') {
                continue;
            }

            $flavor = DB::table('flavors')->where('name_ar', $label)->first();

            if (! $flavor) {
                $flavor = $this->mintFlavor($label, (string) $scoop->slug, (string) $scoop->scoop_family, (bool) $scoop->available);
            }

            DB::table('addons')->where('id', $scoop->id)->update([
                'flavor_id' => $flavor->id,
                // The flavour is the source of truth from here on, so the row's
                // own copy follows it — unless the flavour sits in a family the
                // storefront draws no list for, where staying put is better
                // than disappearing.
                'scoop_family' => isset(Addon::SCOOP_FAMILIES[$flavor->family])
                    ? $flavor->family
                    : $scoop->scoop_family,
                'label'      => $flavor->name_ar,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'flavor_id']);
            $table->dropColumn('flavor_id');
        });

        // The flavours minted above are left alone. By now they may be attached
        // to builder products and switched on and off by the shop, and deleting
        // them to undo a column would take that with them.
    }

    /** A flavour for a scoop that was only ever a line of typing. */
    private function mintFlavor(string $label, string $slug, string $family, bool $available): object
    {
        $id = $this->freeId($label, $slug);

        DB::table('flavors')->insert([
            'id'      => $id,
            'name_ar' => $label,
            // Not nullable, and there is nothing to translate from — the
            // Arabic name stands in until someone fills it in.
            'name_en' => $label,
            'family'  => in_array($family, FlavorFamily::FLAVOR, true) ? $family : 'classic',
            // The switch the row was carrying moves with it, so a flavour that
            // was off stays off rather than quietly coming back on sale.
            'available'             => $available,
            'is_premium_mix_flavor' => false,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        return DB::table('flavors')->where('id', $id)->first();
    }

    /** An id no flavour holds yet, read off the name where the name is Latin. */
    private function freeId(string $label, string $slug): string
    {
        // Str::slug transliterates rather than strips: «موز» → "moz", which
        // reads like the ids the menu already uses. A name it can make nothing
        // of falls back to the identifier the shop gave the row.
        $base = Str::slug($label) ?: Str::slug(Str::after($slug, 'scoop-'));

        // …and "scoop-1" leaves "1", which is no name for a flavour — keep the
        // prefix so the row it came from is still recognisable.
        if ($base === '' || ctype_digit($base)) {
            $base = 'scoop-' . ($base ?: Str::random(4));
        }

        $id = $base;

        for ($n = 2; DB::table('flavors')->where('id', $id)->exists(); $n++) {
            $id = $base . '-' . $n;
        }

        return $id;
    }
};
