<?php

namespace App\Http\Resources;

use App\Models\Addon;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = [
            'id'           => (string) $this->id,
            'slug'         => $this->slug,
            'categoryId'   => $this->category_id,
            'kind'         => $this->kind,
            'name'         => $this->name,
            'image'        => MediaUrl::resolve($this->image),
            'sortOrder'    => $this->sort_order,
            'available'    => (bool) $this->available,
            'hasNotes'     => (bool) $this->has_notes,
            'hasFavorites' => (bool) $this->has_favorites,
            'hasImageZoom' => (bool) $this->has_image_zoom,
            'inStoreOnly'  => (bool) $this->in_store_only,
        ];

        if ($this->description) {
            $base['description'] = $this->description;
        }

        if ($this->relationLoaded('addons')) {
            // Scoops are addons underneath, but the storefront draws them as
            // their own control beside the flavours. Listing them here too
            // would put every flavour on the page twice.
            $addons = $this->addons->reject(fn ($addon) => $addon->isScoop());

            if ($addons->isNotEmpty()) {
                $base['addons'] = AddonResource::collection($addons->values());
            }

            if ($scoops = $this->extraScoop()) {
                $base['extraScoop'] = $scoops;
            }
        }

        if ($this->kind === 'builder') {
            return array_merge($base, $this->builderFields());
        }

        return array_merge($base, $this->flatListFields());
    }

    /**
     * The two flavour lists for "أضف بوظة", or null when this product does not
     * offer one.
     *
     * Absent, not empty: the storefront draws the control only when the field
     * is there, so a product with nothing available must not send the key at
     * all. Unavailable flavours still travel inside a list that has something
     * available, so the customer sees them greyed rather than missing.
     *
     * @return array<string, array<int, array<string, mixed>>>|null
     */
    private function extraScoop(): ?array
    {
        $scoops = $this->addons->filter(fn ($addon) => $addon->isScoop());

        if ($scoops->isEmpty() || $scoops->every(fn ($addon) => ! $addon->available)) {
            return null;
        }

        $families = [];

        foreach (array_keys(Addon::SCOOP_FAMILIES) as $family) {
            $inFamily = $scoops->where('scoop_family', $family)->values();

            if ($inFamily->isEmpty()) {
                continue;
            }

            $families[$family] = $inFamily->map(fn ($addon) => [
                'id'        => $addon->slug,
                'label'     => $addon->label,
                'price'     => (float) $addon->price,
                'available' => (bool) $addon->available,
            ])->all();
        }

        return $families === [] ? null : $families;
    }

    private function builderFields(): array
    {
        $fields = [
            'hasExtraBiscuitAddon' => (bool) $this->has_extra_biscuit_addon,
            'includesIceCreamStep' => (bool) $this->includes_ice_cream_step,
        ];

        if ($this->selection_mode) {
            $fields['selectionMode'] = $this->selection_mode;
        }
        if (!empty($this->flavor_families)) {
            $fields['flavorFamilies'] = $this->flavor_families;
        }
        if ($this->pricing_label) {
            $fields['pricingLabel'] = $this->pricing_label;
        }

        if ($this->relationLoaded('containers') && $this->containers->isNotEmpty()) {
            $fields['containerOptions'] = $this->containers->map(function ($c) {
                $option = [
                    'id'        => $c->slug,
                    'label'     => $c->label,
                    'available' => (bool) $c->available,
                ];
                if ($c->name) {
                    $option['name'] = $c->name;
                }
                if ($url = MediaUrl::resolve($c->image)) {
                    $option['image'] = $url;
                }
                if ($c->pricing_label) {
                    $option['pricingLabel'] = $c->pricing_label;
                }
                return $option;
            })->values();
        }

        if ($this->relationLoaded('sizes')) {
            $fields['sizes'] = $this->sizes->map(function ($size) {
                $s = [
                    'id'        => $size->slug,
                    'label'     => $size->label,
                    'maxBalls'  => $size->max_balls,
                    'available' => (bool) $size->available,
                    'prices'    => $size->prices->map(fn ($p) => [
                        'flavorFamily' => $p->flavor_family,
                        'price'        => $p->price,
                    ])->values(),
                ];
                if ($size->container_slug) {
                    $s['containerId'] = $size->container_slug;
                }
                if ($url = MediaUrl::resolve($size->image)) {
                    $s['image'] = $url;
                }
                return $s;
            })->values();
        }

        if ($this->includes_ice_cream_step && $this->relationLoaded('iceCreamAddonPrices')) {
            $fields['iceCreamAddonPrices'] = $this->iceCreamAddonPrices->map(fn ($p) => [
                'flavorFamily' => $p->flavor_family,
                'price'        => $p->price,
            ])->values();
        }

        if ($this->relationLoaded('flavors') && $this->flavors->isNotEmpty()) {
            $fields['flavors'] = FlavorResource::collection($this->flavors);
        }

        return $fields;
    }

    private function flatListFields(): array
    {
        $fields = [];

        if ($this->relationLoaded('items')) {
            $fields['items'] = $this->items->map(function ($item) {
                $i = [
                    'id'        => $item->slug,
                    'label'     => $item->label,
                    'price'     => $item->price,
                    'available' => (bool) $item->available,
                    'image'     => MediaUrl::resolve($item->image),
                ];
                if ($item->description) {
                    $i['description'] = $item->description;
                }
                if ($item->is_premium_mix_flavor) {
                    $i['isPremiumMixFlavor'] = true;
                }
                return $i;
            })->values();
        }

        if ($this->relationLoaded('mixes') && $this->mixes->isNotEmpty()) {
            $fields['mixes'] = $this->mixes->map(function ($m) {
                return [
                    'id'                 => $m->slug,
                    'label'              => $m->label,
                    'available'          => (bool) $m->available,
                    'pick'               => $m->pick,
                    'basePrice'          => $m->base_price,
                    'flavorPrice'        => $m->flavor_price,
                    'premiumFlavorPrice' => $m->premium_flavor_price,
                    'itemIds'            => $m->item_ids ?? [],
                ];
            })->values();
        }

        return $fields;
    }
}
