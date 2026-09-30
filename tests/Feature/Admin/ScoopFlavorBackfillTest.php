<?php

use App\Models\Addon;
use App\Models\Flavor;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Tests\Support\CatalogFactory;

/**
 * Joining the two flavour lists without losing the one the shop already typed.
 *
 * On production the extra-scoop rows are names typed by hand — «بيستاشيو» with
 * the slug `scoop-1`, «موز» with `scoop-banana`. The migration has to find the
 * flavour each one meant, or make it, and it has to do that without turning
 * anything back on that the shop had switched off.
 */

/** The migration under test, run again over whatever rows exist now. */
function backfillScoops(): void
{
    (require database_path('migrations/2026_09_30_100001_link_scoops_to_flavors.php'))->up();
}

/** A scoop row as it was written before flavours were linked. */
function legacyScoop(Product $product, string $slug, string $label, string $family, bool $available = true): Addon
{
    $addon = $product->addons()->create([
        'slug'         => $slug,
        'label'        => $label,
        'price'        => 9,
        'available'    => $available,
        'type'         => 'toggle',
        'scoop_family' => $family,
    ]);

    // The column the migration fills; a row written before it existed has none.
    DB::table('addons')->where('id', $addon->getKey())->update(['flavor_id' => null]);

    return $addon->refresh();
}

beforeEach(function () {
    $this->crepe = CatalogFactory::flatList('crepe', ['name' => 'كريب']);
});

it('links a typed name to the flavour that already carries it', function () {
    $pistachio = CatalogFactory::flavor('pistachio', ['name_ar' => 'بيستاشيو', 'family' => 'special']);
    $scoop     = legacyScoop($this->crepe, 'scoop-1', 'بيستاشيو', Addon::SCOOP_SPECIAL);

    backfillScoops();

    expect($scoop->refresh()->flavor_id)->toBe($pistachio->id)
        // No second «بيستاشيو» beside the one the menu already had.
        ->and(Flavor::count())->toBe(1);
});

it('makes a flavour of a name nothing in the menu matched', function () {
    $scoop = legacyScoop($this->crepe, 'scoop-banana', 'موز', Addon::SCOOP_CLASSIC);

    backfillScoops();

    $flavor = Flavor::sole();

    expect($scoop->refresh()->flavor_id)->toBe($flavor->id)
        ->and($flavor->name_ar)->toBe('موز')
        ->and($flavor->family)->toBe('classic')
        // Transliterated from the name, so it reads like the ids the menu
        // already uses rather than like the row it came from.
        ->and($flavor->id)->toBe('moz');
});

it('carries a switched-off scoop over as a switched-off flavour', function () {
    legacyScoop($this->crepe, 'scoop-lotus', 'لوتس', Addon::SCOOP_SPECIAL, available: false);

    backfillScoops();

    // Turning it back on would put a flavour the shop had run out of back on
    // sale, on every product, the moment the migration ran.
    expect(Flavor::sole()->available)->toBeFalse();
});

it('gives one flavour to the same name typed on two products', function () {
    $waffle = CatalogFactory::flatList('waffle', ['name' => 'وافل']);

    legacyScoop($this->crepe, 'scoop-1', 'بيستاشيو', Addon::SCOOP_SPECIAL);
    legacyScoop($waffle, 'scoop-2', 'بيستاشيو', Addon::SCOOP_SPECIAL);

    backfillScoops();

    expect(Flavor::count())->toBe(1)
        ->and(Addon::pluck('flavor_id')->unique())->toHaveCount(1);
});

it('falls back to the row when the name transliterates to nothing', function () {
    legacyScoop($this->crepe, 'scoop-1', '★★', Addon::SCOOP_SPECIAL);

    backfillScoops();

    // Nothing to read off the name, and the row leaves "1" — no identifier for
    // a flavour, so the prefix stays and the row it came from is recognisable.
    expect(Flavor::sole()->id)->toBe('scoop-1')
        ->and(Flavor::sole()->name_ar)->toBe('★★');
});

it('steps aside when the menu already holds that identifier', function () {
    // A different flavour, under the id «موز» would transliterate to.
    CatalogFactory::flavor('moz', ['name_ar' => 'موز طازج', 'family' => 'classic']);

    legacyScoop($this->crepe, 'scoop-banana', 'موز', Addon::SCOOP_CLASSIC);

    backfillScoops();

    // Two names, two flavours: the new one takes a suffix rather than
    // overwriting a flavour the menu was already using.
    expect(Flavor::pluck('id')->sort()->values()->all())->toBe(['moz', 'moz-2'])
        ->and(Flavor::find('moz-2')->name_ar)->toBe('موز');
});

it('takes the family from the flavour, not from the row', function () {
    CatalogFactory::flavor('pistachio', ['name_ar' => 'بيستاشيو', 'family' => 'special']);

    // Typed into the wrong list on this product.
    $scoop = legacyScoop($this->crepe, 'scoop-1', 'بيستاشيو', Addon::SCOOP_CLASSIC);

    backfillScoops();

    expect($scoop->refresh()->scoop_family)->toBe(Addon::SCOOP_SPECIAL);
});

it('leaves ordinary addons alone', function () {
    $sauce = $this->crepe->addons()->create([
        'slug' => 'extra-sauce', 'label' => 'صوص', 'price' => 3, 'type' => 'toggle',
    ]);

    backfillScoops();

    expect($sauce->refresh()->flavor_id)->toBeNull()
        ->and(Flavor::count())->toBe(0)
        // A sauce has no flavour behind it and is sold on its own switch.
        ->and($sauce->orderable())->toBeTrue();
});

it('is safe to run twice', function () {
    legacyScoop($this->crepe, 'scoop-banana', 'موز', Addon::SCOOP_CLASSIC);

    backfillScoops();
    backfillScoops();

    expect(Flavor::count())->toBe(1);
});
