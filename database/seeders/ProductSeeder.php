<?php

namespace Database\Seeders;

use App\Models\Addon;
use App\Models\Flavor;
use App\Models\IceCreamAddonPrice;
use App\Models\Product;
use App\Models\ProductContainer;
use App\Models\ProductItem;
use App\Models\ProductMix;
use App\Models\ProductSize;
use App\Models\SizePrice;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing product data (cascades via FK constraints)
        Product::query()->each(fn ($p) => $p->delete());

        $this->seedCup();
        $this->seedFamily();
        $this->seedBrad();
        $this->seedBradBoza();
        $this->seedColdDrinks();
        $this->seedHotDrinks();
        $this->seedJuices();
        $this->seedCorn();
        $this->seedMilkshake();
        $this->seedKunafa();
        $this->seedLoqaimat();
        $this->seedPancake();
        $this->seedWaffle();
        $this->seedCrepe();
        $this->seedPizza();
        $this->seedMolten();
        $this->seedBrownie();
        $this->seedCookies();
        $this->seedCheesecake();
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function makeProduct(array $attrs): Product
    {
        return Product::create(array_merge([
            'available'              => true,
            'has_addons'             => false,
            'has_notes'              => false,
            'has_favorites'          => false,
            'has_image_zoom'         => false,
            'in_store_only'          => false,
            'has_extra_biscuit_addon'=> false,
            'includes_ice_cream_step'=> false,
        ], $attrs));
    }

    private function addContainer(Product $p, string $slug, string $label, bool $available = true, ?string $name = null, ?string $image = null, ?string $pricingLabel = null, int $sort = 0): ProductContainer
    {
        return ProductContainer::create([
            'product_id'    => $p->id,
            'slug'          => $slug,
            'label'         => $label,
            'available'     => $available,
            'name'          => $name,
            'image'         => $image,
            'pricing_label' => $pricingLabel,
            'sort_order'    => $sort,
        ]);
    }

    private function addSize(Product $p, string $slug, string $label, int $maxBalls, array $prices, ?string $containerSlug = null, int $sort = 0): void
    {
        $size = ProductSize::create([
            'product_id'     => $p->id,
            'container_slug' => $containerSlug,
            'slug'           => $slug,
            'label'          => $label,
            'max_balls'      => $maxBalls,
            'sort_order'     => $sort,
        ]);

        foreach ($prices as $family => $price) {
            SizePrice::create(['size_id' => $size->id, 'flavor_family' => $family, 'price' => $price]);
        }
    }

    private function addItem(Product $p, string $slug, string $label, float $price, bool $available = true, bool $isPremium = false, ?string $description = null, ?string $image = null, int $sort = 0): void
    {
        ProductItem::create([
            'product_id'            => $p->id,
            'slug'                  => $slug,
            'label'                 => $label,
            'price'                 => $price,
            'available'             => $available,
            'is_premium_mix_flavor' => $isPremium,
            'description'           => $description,
            'image'                 => $image,
            'sort_order'            => $sort,
        ]);
    }

    private function addMix(Product $p, string $slug, string $label, int $pick, float $base, float $flavorPrice, float $premiumPrice, array $itemIds, int $sort = 0): void
    {
        ProductMix::create([
            'product_id'           => $p->id,
            'slug'                 => $slug,
            'label'                => $label,
            'pick'                 => $pick,
            'base_price'           => $base,
            'flavor_price'         => $flavorPrice,
            'premium_flavor_price' => $premiumPrice,
            'item_ids'             => $itemIds,
            'available'            => true,
            'sort_order'           => $sort,
        ]);
    }

    private function addProductAddon(Product $p, string $slug, string $label, float $price, string $type = 'toggle', ?int $maxQty = null, int $sort = 0): void
    {
        Addon::create([
            'product_id' => $p->id,
            'slug'       => $slug,
            'label'      => $label,
            'price'      => $price,
            'available'  => true,
            'type'       => $type,
            'max_qty'    => $maxQty,
            'sort_order' => $sort,
        ]);
    }

    // ─── 1. بوظة كاسة (cup) — builder ────────────────────────────────────────

    private function seedCup(): void
    {
        $p = $this->makeProduct([
            'slug'                   => 'cup',
            'category_id'            => 'ice-cream',
            'kind'                   => 'builder',
            'name'                   => 'بوظة كاسة',
            'description'            => 'اختر الحاوية والحجم والنكهة المفضلة لديك',
            'image'                  => 'https://cdn.example.com/menu/cup.jpg',
            'sort_order'             => 1,
            'selection_mode'         => 'repeatable',
            'flavor_families'        => ['classic', 'special', 'mix'],
            'has_extra_biscuit_addon'=> true,
            'has_notes'              => true,
        ]);

        $this->addContainer($p, 'cup',      'كاسة',     true, 'بوظة كاسة',     null, 'الكاسة',    0);
        $this->addContainer($p, 'biscuit',  'بسكوت',    true, 'بوظة بسكوت',    'https://cdn.example.com/menu/biscuit.jpg', 'البسكوت', 1);
        $this->addContainer($p, 'takeaway', 'تيك اواي', true, 'بوظة تيك اواي', null, 'التيك اواي', 2);

        $this->addSize($p, 'cup-small',      'صغير',     1, ['classic' => 2, 'special' => 4],              'cup',      0);
        $this->addSize($p, 'cup-medium',     'وسط',      2, ['classic' => 3, 'special' => 5],              'cup',      1);
        $this->addSize($p, 'cup-large',      'كبير',     3, ['classic' => 5, 'special' => 7],              'cup',      2);
        $this->addSize($p, 'biscuit-small',  'صغير',     1, ['classic' => 2],                              'biscuit',  3);
        $this->addSize($p, 'biscuit-medium', 'وسط',      2, ['classic' => 3, 'special' => 5],              'biscuit',  4);
        $this->addSize($p, 'biscuit-large',  'كبير',     3, ['classic' => 5, 'special' => 7],              'biscuit',  5);
        $this->addSize($p, 'takeaway-size',  'تيك اواي', 3, ['classic' => 5, 'special' => 7],              'takeaway', 6);

        // Attach all 23 global flavors
        $p->flavors()->sync(Flavor::pluck('id'));
    }

    // ─── 2. بوظة عائلي (family) — builder ────────────────────────────────────

    private function seedFamily(): void
    {
        $p = $this->makeProduct([
            'slug'                   => 'family',
            'category_id'            => 'ice-cream',
            'kind'                   => 'builder',
            'name'                   => 'بوظة عائلي',
            'description'            => 'حجم عائلي بنكهاتك المفضلة',
            'image'                  => 'https://cdn.example.com/menu/family.jpg',
            'sort_order'             => 2,
            'selection_mode'         => 'repeatable',
            'flavor_families'        => ['classic', 'special', 'mix'],
            'has_extra_biscuit_addon'=> true,
            'has_notes'              => true,
        ]);

        $this->addContainer($p, 'plastic', 'بلاستيك', true,  null, null, null, 0);
        $this->addContainer($p, 'foam',    'فلين',     false, null, null, null, 1);

        $this->addSize($p, 'plastic-half', '1/2 لتر', 8,  ['classic' => 14, 'special' => 18, 'mix' => 16], 'plastic', 0);
        $this->addSize($p, 'plastic-one',  '1 لتر',   12, ['classic' => 28, 'special' => 35, 'mix' => 32], 'plastic', 1);
        $this->addSize($p, 'foam-half',    '1/2 لتر', 8,  ['classic' => 16, 'special' => 20, 'mix' => 18], 'foam',    2);
        $this->addSize($p, 'foam-one',     '1 لتر',   12, ['classic' => 31, 'special' => 38, 'mix' => 35], 'foam',    3);

        // Attach all 23 global flavors (was missing — caused empty flavors step on /menu/order/family)
        $p->flavors()->sync(Flavor::pluck('id'));
    }

    // ─── 3. براد (brad) — builder (no ball picking) ───────────────────────────

    private function seedBrad(): void
    {
        $p = $this->makeProduct([
            'slug'          => 'brad',
            'category_id'   => 'brad',
            'kind'          => 'builder',
            'name'          => 'براد',
            'description'   => 'براد منعش بنكهات متنوعة',
            'image'         => 'https://cdn.example.com/menu/brad.jpg',
            'sort_order'    => 1,
            'pricing_label' => 'أسعار البراد',
            'has_notes'     => true,
        ]);

        $this->addContainer($p, 'lemon', 'ليمون', true, null, null, null, 0);
        $this->addContainer($p, 'mango', 'مانجا', true, null, null, null, 1);
        $this->addContainer($p, 'mix',   'مكس',   true, null, null, null, 2);

        // Sizes apply to all containers (no container_slug)
        $this->addSize($p, 'brad-small',  'صغير', 0, ['classic' => 1], null, 0);
        $this->addSize($p, 'brad-medium', 'وسط',  0, ['classic' => 2], null, 1);
        $this->addSize($p, 'brad-large',  'كبير', 0, ['classic' => 3], null, 2);
    }

    // ─── 4. براد مع بوظة (brad-boza) — builder ───────────────────────────────

    private function seedBradBoza(): void
    {
        $p = $this->makeProduct([
            'slug'                   => 'brad-boza',
            'category_id'            => 'brad-boza',
            'kind'                   => 'builder',
            'name'                   => 'براد مع بوظة',
            'description'            => 'براد منعش مع كرات البوظة بنكهتك المفضلة',
            'image'                  => 'https://cdn.example.com/menu/brad-boza.jpg',
            'sort_order'             => 1,
            'selection_mode'         => 'toggle',
            'flavor_families'        => ['classic', 'special', 'mix'],
            'pricing_label'          => 'أسعار البراد',
            'includes_ice_cream_step'=> true,
            'has_notes'              => true,
        ]);

        $this->addContainer($p, 'lemon', 'ليمون', true, null, null, null, 0);
        $this->addContainer($p, 'mango', 'مانجا', true, null, null, null, 1);
        $this->addContainer($p, 'mix',   'مكس',   true, null, null, null, 2);

        $this->addSize($p, 'brad-boza-small',  'صغير', 2, ['classic' => 1], null, 0);
        $this->addSize($p, 'brad-boza-medium', 'وسط',  3, ['classic' => 2], null, 1);
        $this->addSize($p, 'brad-boza-large',  'كبير', 4, ['classic' => 3], null, 2);

        IceCreamAddonPrice::insert([
            ['product_id' => $p->id, 'flavor_family' => 'classic', 'price' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $p->id, 'flavor_family' => 'special', 'price' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $p->id, 'flavor_family' => 'mix',     'price' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $p->flavors()->sync(Flavor::pluck('id'));
    }

    // ─── 5. مشروبات باردة (cold-drinks) — flat-list ──────────────────────────

    private function seedColdDrinks(): void
    {
        $p = $this->makeProduct([
            'slug'          => 'cold-drinks',
            'category_id'   => 'cold-drinks',
            'kind'          => 'flat-list',
            'name'          => 'مشروبات باردة',
            'image'         => 'https://cdn.example.com/menu/cold-drinks.jpg',
            'sort_order'    => 1,
            'has_favorites' => true,
            'has_image_zoom'=> true,
            'has_notes'     => true,
        ]);

        $this->addItem($p, 'iced-coffee-caramel',  'آيس كوفي كراميل',       8,  true,  false, null, null, 0);
        $this->addItem($p, 'iced-mocha',           'آيس موكا',               8,  true,  false, null, null, 1);
        $this->addItem($p, 'spanish-latte-caramel','سبانش لاتيه كراميل',    10, true,  false, null, null, 2);
        $this->addItem($p, 'boba-shake',           'بوبا شيك كوفي/فراولة',  12, true,  false, null, null, 3);
        $this->addItem($p, 'small-water',          'مياه صغيرة',             1,  true,  false, null, null, 4);
    }

    // ─── 6. مشروبات ساخنة (hot-drinks) — flat-list ───────────────────────────

    private function seedHotDrinks(): void
    {
        $p = $this->makeProduct([
            'slug'          => 'hot-drinks',
            'category_id'   => 'hot-drinks',
            'kind'          => 'flat-list',
            'name'          => 'مشروبات ساخنة',
            'image'         => 'https://cdn.example.com/menu/hot-drinks.jpg',
            'sort_order'    => 1,
            'has_favorites' => true,
            'has_image_zoom'=> true,
            'has_notes'     => true,
        ]);

        $this->addItem($p, 'arabic-coffee', 'قهوة عربية',    5, true, false, null, null, 0);
        $this->addItem($p, 'hot-nescafe',   'نسكافيه حار',   6, true, false, null, null, 1);
        $this->addItem($p, 'tea',           'شاي',           4, true, false, null, null, 2);
        $this->addItem($p, 'hot-chocolate', 'هوت شوكولاتة',  8, true, false, null, null, 3);
    }

    // ─── 7. عصائر طبيعية (juices) — flat-list ────────────────────────────────

    private function seedJuices(): void
    {
        $p = $this->makeProduct([
            'slug'          => 'juices',
            'category_id'   => 'juices',
            'kind'          => 'flat-list',
            'name'          => 'عصائر طبيعية',
            'image'         => 'https://cdn.example.com/menu/juices.jpg',
            'sort_order'    => 1,
            'has_favorites' => true,
            'has_image_zoom'=> true,
            'has_notes'     => true,
        ]);

        $this->addItem($p, 'strawberry',   'فراولة',      5, true, false, null, null, 0);
        $this->addItem($p, 'blue-lemonade','بلوليمونادا', 6, true, false, null, null, 1);
        $this->addItem($p, 'mango',        'مانجا',       7, true, false, null, null, 2);
    }

    // ─── 8. ذرة (corn) — flat-list ───────────────────────────────────────────

    private function seedCorn(): void
    {
        $p = $this->makeProduct([
            'slug'          => 'corn',
            'category_id'   => 'corn',
            'kind'          => 'flat-list',
            'name'          => 'ذرة',
            'image'         => 'https://cdn.example.com/menu/corn.jpg',
            'sort_order'    => 1,
            'has_favorites' => true,
            'has_image_zoom'=> true,
            'has_notes'     => true,
        ]);

        $this->addItem($p, 'plain',     'ذرة سادة',        5, true, false, null, null, 0);
        $this->addItem($p, 'cheese',    'ذرة بالجبنة',     7, true, false, null, null, 1);
        $this->addItem($p, 'chocolate', 'ذرة بالشوكولاتة', 8, true, false, null, null, 2);
    }

    // ─── 9. ميلك شيك (milkshake) — flat-list ────────────────────────────────

    private function seedMilkshake(): void
    {
        $p = $this->makeProduct([
            'slug'          => 'milkshake',
            'category_id'   => 'milkshake',
            'kind'          => 'flat-list',
            'name'          => 'ميلك شيك',
            'image'         => 'https://cdn.example.com/menu/milkshake.jpg',
            'sort_order'    => 1,
            'has_favorites' => true,
            'has_image_zoom'=> true,
        ]);

        $this->addProductAddon($p, 'ms-caramel', 'صوص كراميل إضافي',  3, 'toggle', null, 0);
        $this->addProductAddon($p, 'ms-nutella', 'صوص نوتيلا إضافي',  4, 'toggle', null, 1);
        $this->addProductAddon($p, 'ms-nuts',    'بندق مبشور',         4, 'toggle', null, 2);
        $this->addProductAddon($p, 'ms-oreo',    'قطع أوريو',          3, 'toggle', null, 3);
        $this->addProductAddon($p, 'ms-lotus',   'بسكوت لوتس',         4, 'toggle', null, 4);
        $this->addProductAddon($p, 'ms-cream',   'كريمة مخفوقة',       2, 'toggle', null, 5);

        $this->addItem($p, 'chocolate', 'كلاسيك شوكولاته',        8,  true,  false, null, null, 0);
        $this->addItem($p, 'vanilla',   'كلاسيك فانيلا',           8,  true,  false, null, null, 1);
        $this->addItem($p, 'strawberry','كلاسيك فراولة',           8,  false, false, null, null, 2);
        $this->addItem($p, 'caramel',   'كلاسيك كاراميل',          8,  true,  false, null, null, 3);
        $this->addItem($p, 'nescafe',   'كلاسيك نسكافيه',          8,  true,  false, null, null, 4);
        $this->addItem($p, 'bazooka',   'كلاسيك باروكا',           8,  false, false, null, null, 5);
        $this->addItem($p, 'nutella',   'سبيشال نوتيلا',           10, true,  false, null, null, 6);
        $this->addItem($p, 'lotus',     'سبيشال لوتس',             10, true,  false, null, null, 7);
        $this->addItem($p, 'kinder',    'سبيشال كندر',             10, true,  false, null, null, 8);
        $this->addItem($p, 'oreo',      'سبيشال أوريو',            10, false, false, null, null, 9);
        $this->addItem($p, 'kitkat',    'سبيشال كت كات',           10, true,  false, null, null, 10);
        $this->addItem($p, 'fitness',   'سبيشال فيتنس',            10, true,  false, null, null, 11);
        $this->addItem($p, 'oat',       'سبيشال شوفان',            10, true,  false, null, null, 12);
        $this->addItem($p, 'cerelac',   'سيرلاك (أطعم خاصة)',      8,  true,  false, null, null, 13);
        $this->addItem($p, 'einstein',  'اينشتاين (أطعم خاصة)',    9,  true,  false, null, null, 14);
        $this->addItem($p, 'pistachio', 'بيستاشيو (أطعم خاصة)',   13, true,  false, null, null, 15);
    }

    // ─── 10. كنافة آيس كريم (kunafa) — flat-list ─────────────────────────────

    private function seedKunafa(): void
    {
        $p = $this->makeProduct([
            'slug'          => 'kunafa',
            'category_id'   => 'kunafa',
            'kind'          => 'flat-list',
            'name'          => 'كنافة آيس كريم',
            'image'         => 'https://cdn.example.com/menu/kunafa.jpg',
            'sort_order'    => 1,
            'has_favorites' => true,
            'has_notes'     => true,
        ]);

        $this->addItem($p, 'arabian',            'كنافة عربية',                8,  true,  false, null, null, 0);
        $this->addItem($p, 'lotus',              'كنافة لوتس',                 8,  true,  false, null, null, 1);
        $this->addItem($p, 'nutella',            'كنافة نوتيلا',               8,  true,  false, null, null, 2);
        $this->addItem($p, 'blueberry',          'كنافة بلوبيري',              8,  false, false, null, null, 3);
        $this->addItem($p, 'dondurma-pistachio', 'كنافة دوندورما بيستاشيو',   12, true,  true,  null, null, 4);
        $this->addItem($p, 'energy',             'كنافة طاقة (كل خميس)',      12, false, false, null, null, 5);

        $this->addMix($p, 'mix', 'مكس (اختر طعمين)', 2, 10, 5, 8,
            ['arabian', 'lotus', 'nutella', 'blueberry', 'dondurma-pistachio', 'energy'], 0);
    }

    // ─── 11. لقيمات (loqaimat) — flat-list ───────────────────────────────────

    private function seedLoqaimat(): void
    {
        $p = $this->makeProduct([
            'slug'          => 'loqaimat',
            'category_id'   => 'loqaimat',
            'kind'          => 'flat-list',
            'name'          => 'لقيمات',
            'image'         => 'https://cdn.example.com/menu/loqaimat.jpg',
            'sort_order'    => 1,
            'has_favorites' => true,
            'has_notes'     => true,
        ]);

        $this->addItem($p, 'arabian',            'لقيمة عربية',                8,  true,  false, null, null, 0);
        $this->addItem($p, 'lotus',              'لقيمة لوتس',                 8,  true,  false, null, null, 1);
        $this->addItem($p, 'nutella',            'لقيمة نوتيلا',               8,  true,  false, null, null, 2);
        $this->addItem($p, 'blueberry',          'لقيمة بلوبيري',              8,  false, false, null, null, 3);
        $this->addItem($p, 'dondurma-pistachio', 'لقيمة دوندورما بيستاشيو',   12, true,  true,  null, null, 4);
        $this->addItem($p, 'energy',             'لقيمة طاقة (كل خميس)',      12, false, false, null, null, 5);

        $allIds = ['arabian', 'lotus', 'nutella', 'blueberry', 'dondurma-pistachio', 'energy'];
        $this->addMix($p, 'mix',       'مكس (اختر طعمين)',             2, 10, 5, 8, $allIds, 0);
        $this->addMix($p, 'super-mix', 'سوبر مكس (اختر ثلاثة أطعمة)', 3, 15, 5, 8, $allIds, 1);
    }

    // ─── 12. بان كيك (pancake) — flat-list ───────────────────────────────────

    private function seedPancake(): void
    {
        $p = $this->makeProduct([
            'slug'          => 'pancake',
            'category_id'   => 'pancake',
            'kind'          => 'flat-list',
            'name'          => 'بان كيك',
            'description'   => 'بان كيك طازج بنكهات متنوعة',
            'image'         => 'https://cdn.example.com/menu/pancake.jpg',
            'sort_order'    => 1,
            'in_store_only' => true,
            'has_favorites' => true,
            'has_notes'     => true,
        ]);

        $this->addProductAddon($p, 'pk-nutella', 'صوص نوتيلا إضافي', 4, 'toggle', null, 0);
        $this->addProductAddon($p, 'pk-nuts',    'بندق مبشور',        4, 'toggle', null, 1);

        $this->addItem($p, 'nutella',   'نوتيلا',    11, true, false, null, null, 0);
        $this->addItem($p, 'lotus',     'لوتس',      13, true, false, null, null, 1);
        $this->addItem($p, 'pistachio', 'بيستاشيو',  17, true, true,  null, null, 2);

        $ids = ['nutella', 'lotus', 'pistachio'];
        $this->addMix($p, 'mix',       'مكس (اختر طعمين)',             2, 14, 7, 11, $ids, 0);
        $this->addMix($p, 'super-mix', 'سوبر مكس (اختر ثلاثة أطعمة)', 3, 18, 6, 10, $ids, 1);
    }

    // ─── 13. وافل (waffle) — flat-list ───────────────────────────────────────

    private function seedWaffle(): void
    {
        $p = $this->makeProduct([
            'slug'          => 'waffle',
            'category_id'   => 'waffle',
            'kind'          => 'flat-list',
            'name'          => 'وافل',
            'description'   => 'وافل مقرمش بنكهات متنوعة',
            'image'         => 'https://cdn.example.com/menu/waffle.jpg',
            'sort_order'    => 1,
            'in_store_only' => true,
            'has_favorites' => true,
            'has_notes'     => true,
        ]);

        $this->addItem($p, 'nutella',   'نوتيلا',    10, true, false, null, null, 0);
        $this->addItem($p, 'lotus',     'لوتس',      12, true, false, null, null, 1);
        $this->addItem($p, 'pistachio', 'بيستاشيو',  14, true, true,  null, null, 2);

        $ids = ['nutella', 'lotus', 'pistachio'];
        $this->addMix($p, 'mix',       'مكس (اختر طعمين)',             2, 14, 7, 11, $ids, 0);
        $this->addMix($p, 'super-mix', 'سوبر مكس (اختر ثلاثة أطعمة)', 3, 15, 5, 9,  $ids, 1);
    }

    // ─── 14. كريب (crepe) — flat-list ────────────────────────────────────────

    private function seedCrepe(): void
    {
        $p = $this->makeProduct([
            'slug'          => 'crepe',
            'category_id'   => 'crepe',
            'kind'          => 'flat-list',
            'name'          => 'كريب',
            'description'   => 'كريب رفيع بنكهات متنوعة',
            'image'         => 'https://cdn.example.com/menu/crepe.jpg',
            'sort_order'    => 1,
            'in_store_only' => true,
            'has_favorites' => true,
            'has_notes'     => true,
        ]);

        $this->addItem($p, 'nutella',   'نوتيلا',    9,  true, false, null, null, 0);
        $this->addItem($p, 'lotus',     'لوتس',      11, true, false, null, null, 1);
        $this->addItem($p, 'pistachio', 'بيستاشيو',  13, true, true,  null, null, 2);

        $ids = ['nutella', 'lotus', 'pistachio'];
        $this->addMix($p, 'mix',       'مكس (اختر طعمين)',             2, 12, 6, 10, $ids, 0);
        $this->addMix($p, 'super-mix', 'سوبر مكس (اختر ثلاثة أطعمة)', 3, 15, 5, 9,  $ids, 1);
    }

    // ─── 15. بيتزا جلاسيه (pizza) — flat-list ────────────────────────────────

    private function seedPizza(): void
    {
        $p = $this->makeProduct([
            'slug'          => 'pizza',
            'category_id'   => 'pizza',
            'kind'          => 'flat-list',
            'name'          => 'بيتزا جلاسيه',
            'description'   => 'بيتزا آيس كريم بنكهات مميزة',
            'image'         => 'https://cdn.example.com/menu/pizza.jpg',
            'sort_order'    => 1,
            'in_store_only' => true,
            'has_favorites' => true,
            'has_notes'     => true,
        ]);

        $this->addItem($p, 'nutella',   'نوتيلا',    12, true, false, null, null, 0);
        $this->addItem($p, 'lotus',     'لوتس',      14, true, false, null, null, 1);
        $this->addItem($p, 'pistachio', 'بيستاشيو',  16, true, true,  null, null, 2);

        $ids = ['nutella', 'lotus', 'pistachio'];
        $this->addMix($p, 'mix',       'مكس (اختر طعمين)',             2, 16, 8, 12, $ids, 0);
        $this->addMix($p, 'super-mix', 'سوبر مكس (اختر ثلاثة أطعمة)', 3, 18, 6, 10, $ids, 1);
    }

    // ─── 16. مولتن كيك (molten) — flat-list ──────────────────────────────────

    private function seedMolten(): void
    {
        $p = $this->makeProduct([
            'slug'          => 'molten',
            'category_id'   => 'molten',
            'kind'          => 'flat-list',
            'name'          => 'مولتن كيك',
            'image'         => 'https://cdn.example.com/menu/molten.jpg',
            'sort_order'    => 1,
            'in_store_only' => true,
            'has_favorites' => true,
            'has_notes'     => true,
        ]);

        $this->addItem($p, 'nutella',   'نوتيلا',   8,  true, false, 'كيك شوكولاتة دافئ بقلب سائل مع بوظة فانيلا', null, 0);
        $this->addItem($p, 'lotus',     'لوتس',     12, true, false, 'كيك شوكولاتة دافئ بقلب سائل مع بوظة لوتس',    null, 1);
        $this->addItem($p, 'pistachio', 'بستاشيو',  12, true, false, 'كيك شوكولاتة دافئ بقلب سائل مع بوظة بستاشيو', null, 2);
    }

    // ─── 17. براونيز (brownie) — flat-list ───────────────────────────────────

    private function seedBrownie(): void
    {
        $p = $this->makeProduct([
            'slug'          => 'brownie',
            'category_id'   => 'desserts',
            'kind'          => 'flat-list',
            'name'          => 'براونيز',
            'image'         => 'https://cdn.example.com/menu/brownie.jpg',
            'sort_order'    => 1,
            'has_favorites' => true,
            'has_image_zoom'=> true,
        ]);

        $this->addItem($p, 'plain',   'براونيز عادي',   8,  true, false, null, null, 0);
        $this->addItem($p, 'nutella', 'براونيز نوتيلا', 10, true, false, null, null, 1);
        $this->addItem($p, 'lotus',   'براونيز لوتس',   10, true, false, null, null, 2);
    }

    // ─── 18. كوكيز (cookies) — flat-list ─────────────────────────────────────

    private function seedCookies(): void
    {
        $p = $this->makeProduct([
            'slug'          => 'cookies',
            'category_id'   => 'desserts',
            'kind'          => 'flat-list',
            'name'          => 'كوكيز',
            'image'         => 'https://cdn.example.com/menu/cookies.jpg',
            'sort_order'    => 2,
            'has_favorites' => true,
            'has_image_zoom'=> true,
        ]);

        $this->addItem($p, 'nutella',   'كوكيز نوتيلا',   8,  true, false, null, null, 0);
        $this->addItem($p, 'lotus',     'كوكيز لوتس',      10, true, false, null, null, 1);
        $this->addItem($p, 'pistachio', 'كوكيز بيستاشيو', 12, true, false, null, null, 2);
        $this->addItem($p, 'mix',       'كوكيز مكس',       10, true, false, null, null, 3);
    }

    // ─── 19. تشيز كيك (cheesecake) — flat-list ───────────────────────────────

    private function seedCheesecake(): void
    {
        $p = $this->makeProduct([
            'slug'          => 'cheesecake',
            'category_id'   => 'desserts',
            'kind'          => 'flat-list',
            'name'          => 'تشيز كيك',
            'image'         => 'https://cdn.example.com/menu/cheesecake.jpg',
            'sort_order'    => 3,
            'has_favorites' => true,
            'has_image_zoom'=> true,
        ]);

        $this->addItem($p, 'strawberry', 'تشيز كيك فراولة',   12, true, false, null, null, 0);
        $this->addItem($p, 'lotus',      'تشيز كيك لوتس',      14, true, false, null, null, 1);
        $this->addItem($p, 'pistachio',  'تشيز كيك بيستاشيو', 16, true, false, null, null, 2);
        $this->addItem($p, 'mix',        'تشيز كيك مكس',       14, true, false, null, null, 3);
    }
}
