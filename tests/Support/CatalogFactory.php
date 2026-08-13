<?php

namespace Tests\Support;

use App\Models\Event;
use App\Models\EventImage;
use App\Models\Flavor;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\ProductContainer;
use App\Models\ProductItem;
use App\Models\ProductMix;
use App\Models\ProductSize;
use App\Models\SizePrice;

/**
 * Minimal catalog builders for the API/dashboard contract tests.
 *
 * The real seeders are intentionally not used here: they hold the production
 * menu and would make assertions depend on business data that changes.
 */
class CatalogFactory
{
    public static function category(string $id = 'pancake'): MenuCategory
    {
        return MenuCategory::firstOrCreate(['id' => $id], [
            'label'         => 'بان كيك',
            'icon'          => 'cake',
            'accent_color'  => '#f4a851',
            'gradient_from' => '#f4a851',
            'gradient_to'   => '#c97d2a',
            'sort_order'    => 1,
            'available'     => true,
        ]);
    }

    public static function flatList(string $slug = 'pancake', array $attributes = []): Product
    {
        return Product::create(array_merge([
            'slug'        => $slug,
            'category_id' => self::category()->id,
            'kind'        => 'flat-list',
            'name'        => 'بان كيك',
            'image'       => 'products/pancake.png',
            'sort_order'  => 1,
            'available'   => true,
        ], $attributes));
    }

    public static function builder(string $slug = 'cup', array $attributes = []): Product
    {
        return Product::create(array_merge([
            'slug'            => $slug,
            'category_id'     => self::category()->id,
            'kind'            => 'builder',
            'name'            => 'بوظة كاسة',
            'image'           => 'products/cup.png',
            'sort_order'      => 1,
            'available'       => true,
            'selection_mode'  => 'repeatable',
            'flavor_families' => ['classic', 'special'],
        ], $attributes));
    }

    public static function item(Product $product, string $slug, array $attributes = []): ProductItem
    {
        return ProductItem::create(array_merge([
            'product_id' => $product->id,
            'slug'       => $slug,
            'label'      => 'صنف ' . $slug,
            'price'      => 8,
            'available'  => true,
            'image'      => 'items/' . $slug . '.png',
            'sort_order' => 1,
        ], $attributes));
    }

    public static function mix(Product $product, string $slug, array $itemIds, array $attributes = []): ProductMix
    {
        return ProductMix::create(array_merge([
            'product_id'           => $product->id,
            'slug'                 => $slug,
            'label'                => 'مكس',
            'pick'                 => 2,
            'base_price'           => 14,
            'flavor_price'         => 7,
            'premium_flavor_price' => 11,
            'item_ids'             => $itemIds,
            'available'            => true,
            'sort_order'           => 1,
        ], $attributes));
    }

    public static function container(Product $product, string $slug, array $attributes = []): ProductContainer
    {
        return ProductContainer::create(array_merge([
            'product_id' => $product->id,
            'slug'       => $slug,
            'label'      => 'حاوية ' . $slug,
            'available'  => true,
            'sort_order' => 1,
        ], $attributes));
    }

    public static function size(Product $product, string $slug, array $prices = ['classic' => 2], array $attributes = []): ProductSize
    {
        $size = ProductSize::create(array_merge([
            'product_id' => $product->id,
            'slug'       => $slug,
            'label'      => 'صغير',
            'max_balls'  => 1,
            'available'  => true,
            'sort_order' => 1,
        ], $attributes));

        foreach ($prices as $family => $price) {
            SizePrice::create([
                'size_id'       => $size->id,
                'flavor_family' => $family,
                'price'         => $price,
            ]);
        }

        return $size;
    }

    public static function flavor(string $id = 'mango', array $attributes = []): Flavor
    {
        return Flavor::create(array_merge([
            'id'        => $id,
            'name_ar'   => 'مانجا',
            'name_en'   => 'Mango',
            'image'     => 'flavors/' . $id . '.png',
            'family'    => 'classic',
            'available' => true,
        ], $attributes));
    }

    public static function event(array $attributes = []): Event
    {
        return Event::create(array_merge([
            'title'       => 'فعالية',
            'date'        => '2020-06-11',
            'description' => 'وصف الفعالية',
            'list_image'  => 'events/card.png',
        ], $attributes));
    }

    public static function eventImage(Event $event, string $path, int $sort = 1): EventImage
    {
        return EventImage::create([
            'event_id'   => $event->id,
            'image_url'  => $path,
            'sort_order' => $sort,
        ]);
    }
}
