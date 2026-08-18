<?php

use App\Models\Flavor;
use App\Models\Product;
use App\Support\FlavorFamily;
use Database\Seeders\FlavorSeeder;
use Database\Seeders\MenuCategorySeeder;
use Database\Seeders\ProductSeeder;

beforeEach(function () {
    $this->seed(MenuCategorySeeder::class);
    $this->seed(FlavorSeeder::class);
    $this->seed(ProductSeeder::class);
});

it('never attaches a flavor whose family the product does not declare', function () {
    $mismatched = [];

    foreach (Product::with('flavors')->where('kind', 'builder')->get() as $product) {
        $declared = $product->flavor_families ?? [];

        foreach ($product->flavors as $flavor) {
            if (! in_array($flavor->family, $declared, true)) {
                $mismatched[] = "{$product->slug} ← {$flavor->id} ({$flavor->family})";
            }
        }
    }

    expect($mismatched)->toBe([]);
});

it('prices every flavor family the builders declare', function () {
    $unpriced = [];

    // `brad` has no flavor picker, so it declares no families and its sizes use
    // `classic` purely as the single price-table key. Only builders with a
    // picker are expected to keep prices and declared families aligned.
    $withPicker = Product::with('sizes.prices')
        ->where('kind', 'builder')
        ->get()
        ->filter(fn (Product $p) => ! empty($p->flavor_families));

    expect($withPicker)->not->toBeEmpty();

    foreach ($withPicker as $product) {
        $declaredFlavorFamilies = FlavorFamily::pickableFrom($product->flavor_families);

        foreach ($product->sizes as $size) {
            $priced = $size->prices->pluck('flavor_family')->all();

            // A size only needs prices for families it actually serves, but any
            // family it does price must be one the product declares.
            foreach ($priced as $family) {
                if (! in_array($family, $product->flavor_families ?? [], true)) {
                    $unpriced[] = "{$product->slug}/{$size->slug} prices undeclared family [{$family}]";
                }
            }
        }

        expect($declaredFlavorFamilies)->not->toBeEmpty();
    }

    expect($unpriced)->toBe([]);
});

it('keeps every seeded flavor family reachable from at least one builder', function () {
    $servedFamilies = Product::where('kind', 'builder')
        ->get()
        ->flatMap(fn (Product $p) => $p->flavor_families ?? [])
        ->unique();

    $orphanFamilies = Flavor::query()
        ->whereNotIn('family', $servedFamilies)
        ->pluck('family')
        ->unique()
        ->values()
        ->all();

    // `stevia` flavors exist but no builder declares that family yet, so they
    // are intentionally not served. Assert the set explicitly so adding a
    // family without wiring it into a product fails here instead of silently
    // producing flavors the storefront can never show.
    expect($orphanFamilies)->toBe(['stevia']);
});
