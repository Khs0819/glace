<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = [
            'id'            => (string) $this->id,
            'slug'          => $this->slug,
            'categoryId'    => $this->category_id,
            'kind'          => $this->kind,
            'name'          => $this->name,
            'image'         => $this->image,
            'sortOrder'     => $this->sort_order,
            'available'     => $this->available,
            'hasNotes'      => $this->has_notes,
            'hasFavorites'  => $this->has_favorites,
            'hasImageZoom'  => $this->has_image_zoom,
            'inStoreOnly'   => $this->in_store_only,
        ];

        if ($this->description) {
            $base['description'] = $this->description;
        }

        // Per-product addons
        if ($this->relationLoaded('addons') && $this->addons->isNotEmpty()) {
            $base['addons'] = AddonResource::collection($this->addons);
        }

        if ($this->kind === 'builder') {
            return array_merge($base, $this->builderFields());
        }

        return array_merge($base, $this->flatListFields());
    }

    private function builderFields(): array
    {
        $fields = [
            'selectionMode'         => $this->selection_mode,
            'flavorFamilies'        => $this->flavor_families ?? [],
            'hasExtraBiscuitAddon'  => $this->has_extra_biscuit_addon,
            'includesIceCreamStep'  => $this->includes_ice_cream_step,
        ];

        if ($this->pricing_label) {
            $fields['pricingLabel'] = $this->pricing_label;
        }

        // Container options
        if ($this->relationLoaded('containers') && $this->containers->isNotEmpty()) {
            $fields['containerOptions'] = $this->containers->map(fn ($c) => array_filter([
                'id'           => $c->slug,
                'label'        => $c->label,
                'available'    => $c->available,
                'name'         => $c->name,
                'image'        => $c->image,
                'pricingLabel' => $c->pricing_label,
            ], fn ($v) => $v !== null));
        }

        // Sizes with prices
        if ($this->relationLoaded('sizes')) {
            $fields['sizes'] = $this->sizes->map(function ($size) {
                $s = [
                    'id'       => $size->slug,
                    'label'    => $size->label,
                    'maxBalls' => $size->max_balls,
                    'prices'   => $size->prices->map(fn ($p) => [
                        'flavorFamily' => $p->flavor_family,
                        'price'        => $p->price,
                    ])->values(),
                ];
                if ($size->container_slug) {
                    $s['containerId'] = $size->container_slug;
                }
                return $s;
            });
        }

        // Ice cream addon prices (brad-boza)
        if ($this->includes_ice_cream_step && $this->relationLoaded('iceCreamAddonPrices')) {
            $fields['iceCreamAddonPrices'] = $this->iceCreamAddonPrices->map(fn ($p) => [
                'flavorFamily' => $p->flavor_family,
                'price'        => $p->price,
            ])->values();
        }

        return $fields;
    }

    private function flatListFields(): array
    {
        $fields = [];

        // Items
        if ($this->relationLoaded('items')) {
            $fields['items'] = $this->items->map(function ($item) {
                $i = [
                    'label'     => $item->label,
                    'price'     => $item->price,
                    'available' => $item->available,
                ];
                if ($item->description) {
                    $i['description'] = $item->description;
                }
                if ($item->image) {
                    $i['image'] = $item->image;
                }
                if ($item->is_premium_mix_flavor) {
                    $i['isPremiumMixFlavor'] = true;
                }
                return $i;
            });
        }

        // Mixes
        if ($this->relationLoaded('mixes') && $this->mixes->isNotEmpty()) {
            $fields['mixes'] = $this->mixes->map(fn ($m) => [
                'id'                 => $m->slug,
                'label'              => $m->label,
                'pick'               => $m->pick,
                'basePrice'          => $m->base_price,
                'flavorPrice'        => $m->flavor_price,
                'premiumFlavorPrice' => $m->premium_flavor_price,
                'flavorOptionIds'    => $m->flavor_option_ids,
            ])->values();
        }

        return $fields;
    }
}
