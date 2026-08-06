# 🍦 Glace Menu Catalog & API Contract

**For Laravel Backend Developers**

This document is the complete specification for the menu API. It defines:
- Every category, product, flavor, size, price, and item
- The exact JSON structure the frontend expects
- How availability control works
- All edge-case scenarios the frontend relies on

**The backend's job:** Return JSON shaped exactly like the examples below at the three endpoints, make every field optional/controllable via a dashboard, and never crash the frontend on invalid data. That's it.

---

## API Endpoints & Data Flow

| Endpoint | Frontend Hook | Returns | Notes |
|---|---|---|---|
| `GET /api/menu/categories` | `useMenuCategories()` | `IMenuCategory[]` | Browse-grid tabs. ~50ms, cache 5min. |
| `GET /api/menu/products?category={id}` | `useMenuProducts(id)` | `IProduct[]` | All products in category, or all if no param. Filter by `categoryId`. |
| `GET /api/menu/products/{slug}` | `useMenuProduct(slug)` | `IProduct \| null` | Single product for order page, looked up by **`slug`** (route-model-bind on the `slug` column, NOT the PK). Returns `null` if not found (not 404). |
| `GET /api/menu/addons` | `useMenuAddons()` | `IAddonOption[]` | Shared additions (إضافات) catalog with prices (sauces, nuts, biscuit…) for the cart's per-unit "تخصيص الإضافات" flow. A product may override via its own `addons`. |

All three endpoints:
1. Frontend tries the real API **first**.
2. On network error, timeout, or invalid JSON: `console.error` and return fake data.
3. Bad rows in a list are dropped individually; one malformed product doesn't blank a category.
4. **Never returns `null` or empty object on success—always an array or typed object.**

---

## Type Definitions (TypeScript / Backend Contract)

### IMenuCategory
**Browse-grid tabs. 16 total. Immutable; backend doesn't need a CRUD dashboard for these.**

```json
{
  "id": "pancake",
  "label": "بان كيك",
  "icon": "cake",
  "accentColor": "#f4a851",
  "gradientFrom": "#f4a851",
  "gradientTo": "#c97d2a",
  "sortOrder": 11
}
```

| Field | Type | Example | Notes |
|---|---|---|---|
| `id` | string | "pancake" | Stable slug, globally unique. Never changes. |
| `label` | string | "بان كيك" | Arabic label shown in UI. |
| `icon` | enum | "cake" | One of: `ice-cream`, `cup-soda`, `cake`, `glass-water`, `milk`, `apple`. |
| `accentColor` | hex | "#f4a851" | Button/hover color. |
| `gradientFrom` | hex | "#f4a851" | Top of category-page gradient. |
| `gradientTo` | hex | "#c97d2a" | Bottom of category-page gradient. |
| `sortOrder` | int | 11 | Browse-grid order. 1–16. |

---

### IFlavorOption
**Global flavor catalog for builder-template products (cup, family, brad-boza).**
**23 total. Every one can be toggled available/unavailable via dashboard.**

```json
{
  "id": "pistachio",
  "nameAr": "بيستاشيو",
  "nameEn": "Pistachio",
  "image": "https://cdn.glace.com/flavors/pistachio.jpg",
  "family": "special",
  "available": true,
  "isPremiumMixFlavor": true
}
```

| Field | Type | Example | Notes |
|---|---|---|---|
| `id` | string | "pistachio" | Stable slug. Used in mix rules' `flavorOptionIds`. |
| `nameAr` | string | "بيستاشيو" | Arabic label on flavor-ball picker. |
| `nameEn` | string | "Pistachio" | English label (optional, for backend logs). |
| `image` | string (URL) | "https://..." | Flavor photo. Can be static or CDN. |
| `family` | enum | "special" | `"classic"` or `"special"` or `"stevia"`. Stevia flavors fold into classic picker. |
| `available` | boolean | true | If false, flavor renders greyed with "غير متوفر" badge. Still clickable, but can't be picked. |
| `isPremiumMixFlavor` | boolean | true | If true, charges the mix's `premiumFlavorPrice` instead of `flavorPrice`. Only pistachio has this today. |

**Flavor list by family (23 total):**
- **Classic (13):** chocolate, vanilla, strawberry, caramel, dark-chocolate, nescafe, coconut, mango *(unavailable)*, banana, grape, bazooka, mario, lemon
- **Stevia (2, folds into classic picker):** vanilla-stevia, nescafe-stevia
- **Special (8):** arabian, nutella, oreo, kitkat, flora *(unavailable)*, kinder, lotus, pistachio *(premium)*

---

### IProductBase
**Fields shared by every product variant (builder or flat-list).**

```json
{
  "id": "b7f1c2a4-9e3d-4a10-8c21-3f5a2d1e9b04",
  "slug": "cup",
  "categoryId": "ice-cream",
  "name": "بوظة كاسة",
  "description": "اختر الحاوية والحجم والنكهة المفضلة لديك",
  "image": "https://cdn.glace.com/products/cup.jpg",
  "sortOrder": 1,
  "available": true,
  "addons": [
    { "id": "extra-caramel", "label": "صوص كراميل", "price": 3, "available": true }
  ],
  "hasAddons": false,
  "hasNotes": true,
  "hasFavorites": false,
  "hasImageZoom": false,
  "inStoreOnly": false
}
```

| Field | Type | Example | Notes |
|---|---|---|---|
| `id` | string | "b7f1c2a4-…" | **Opaque backend primary key** (auto-increment int or UUID). Referenced by cart/order/favorites, sent back to the API — **never** appears in a URL. Do NOT assume it equals `slug`. |
| `slug` | string | "cup" | **Stable, URL-safe identifier.** Matched against `/menu/order/{type}` and used to fetch the product (`GET /menu/products/{slug}`). Must be unique and stable — renaming `name` must NOT change it. |
| `categoryId` | string | "ice-cream" | Foreign key to `IMenuCategory.id`. |
| `name` | string | "بوظة كاسة" | Product name on order page. |
| `description` | string | "اختر الحاوية…" | Optional. Short product description shown on the order page. |
| `image` | string (URL) | "https://..." | Hero image on order page. |
| `sortOrder` | int | 1 | Order within category. |
| `available` | boolean | true | If false, product doesn't appear in browse grid and order page shows "غير متوفر". |
| `addons` | array | see `IAddonOption` | Optional per-unit extras catalog (toppings/sauces). Drives the cart's "تخصيص الإضافات" flow. See below. |
| `hasAddons` | boolean | false | Legacy flag — superseded by a non-empty `addons` array. |
| `hasNotes` | boolean | true | Shows "أضف ملاحظة" textarea before qty stepper. |
| `hasFavorites` | boolean | false | Shows heart favorite button on items (flat-list only). |
| `hasImageZoom` | boolean | false | Tapping image opens a zoom dialog (flat-list only). |
| `inStoreOnly` | boolean | false | If true, shows in-store-only warning before order flow (e.g., pancake, waffle). |

#### IAddonOption (additions / إضافات)
Per-unit extras (with prices) a customer can add to a cart line. The frontend loads a **shared catalog** from `GET /menu/addons` (`useMenuAddons()`) and applies it to any product; a product MAY override it with its own `addons[]` on the product payload. In the cart, the customer can apply one addition set to **all** units of a line, or **different** additions per individual unit (e.g. 4 milkshakes, each with its own toppings). The catalog includes a biscuit (بسكوت) option.

```json
{ "id": "extra-caramel", "label": "صوص كراميل إضافي", "price": 3, "available": true, "type": "toggle" }
{ "id": "extra-biscuit", "label": "بسكوت مخروط", "price": 3, "available": true, "type": "counter", "maxQty": 10 }
```

| Field | Type | Notes |
|---|---|---|
| `id` | string | Stable slug for the addon (e.g. `extra-caramel`). |
| `label` | string | Display name (Arabic). |
| `price` | number | Per-unit surcharge in ₪, charged **per selected quantity**. |
| `available` | boolean | Optional. If `false`, hidden from the picker. |
| `type` | enum | Optional. `"toggle"` (on/off checkbox, default) or `"counter"` (+/- quantity stepper, e.g. extra biscuit ×3). **Set per-addon in the dashboard.** |
| `maxQty` | int | Optional. Max quantity per unit for `counter` addons. |

> **Cart representation (client-side today):** the cart lives in the browser (localStorage), so per-unit additions are not persisted server-side yet. When the order/checkout endpoint is built, each ordered unit must carry its own selected addon ids + the product's `id` (PK), so a line of quantity N can have up to N distinct addition sets.

---

### IBuilderProduct
**Wizard template: size/container/flavor-ball step-card flow (cup, family, brad, brad-boza).**

Extends `IProductBase` with:

```json
{
  "kind": "builder",
  "selectionMode": "repeatable",
  "flavorFamilies": ["classic", "special", "mix"],
  "pricingLabel": "أسعار الكاسة",
  "containerOptions": [
    {
      "id": "cup",
      "label": "كاسة",
      "available": true,
      "name": "بوظة كاسة",
      "image": "https://cdn.glace.com/containers/cup.jpg",
      "pricingLabel": "الكاسة"
    }
  ],
  "sizes": [
    {
      "id": "cup-small",
      "label": "صغير",
      "maxBalls": 1,
      "containerId": "cup",
      "prices": [
        { "flavorFamily": "classic", "price": 2 },
        { "flavorFamily": "special", "price": 4 }
      ]
    }
  ],
  "hasExtraBiscuitAddon": true,
  "includesIceCreamStep": false,
  "iceCreamAddonPrices": []
}
```

| Field | Type | Notes |
|---|---|---|
| `kind` | string | Must be `"builder"`. |
| `selectionMode` | enum | `"repeatable"` (same flavor ×N times) or `"toggle"` (each flavor ≤1). Cup/Family are repeatable; brad-boza is toggle. |
| `flavorFamilies` | string[] | Array of `"classic"`, `"special"`, `"mix"`. Order is displayed order. |
| `pricingLabel` | string | Heading for price table when all sizes share one table (e.g., "أسعار البراد"). Unused if sizes carry `containerId`. |
| `containerOptions` | array | Containers (e.g., كاسة/بسكوت, بلاستيك/فلين). Each has `id`, `label`, `available`, optional `name` (override product name), optional `image`, optional `pricingLabel`. |
| `sizes` | array | Size rows. Each has `id`, `label`, `maxBalls` (flavor-ball count; 0 if no picker), `containerId` (optional, filters to one container), `prices` (grid of `{flavorFamily, price}`). |
| `hasExtraBiscuitAddon` | boolean | If true, qty stepper shows +1/−1 extra-biscuit counter at 1₪ each. |
| `includesIceCreamStep` | boolean | Brad-boza only. If true, adds extra "أضف بوظة" step after size. |
| `iceCreamAddonPrices` | array | Brad-boza only. Additive prices (classic/special/mix) for the ice-cream step, added to base size price. |

---

### IFlatListProduct
**Flat items + optional mixes (milkshake, kunafa, pancake, desserts, drinks, etc.).**

Extends `IProductBase` with:

```json
{
  "kind": "flat-list",
  "items": [
    {
      "label": "نوتيلا",
      "price": 11,
      "description": "كيك دافئ مع بوظة",
      "image": "https://cdn.glace.com/items/nutella-pancake.jpg",
      "available": true,
      "isPremiumMixFlavor": false
    }
  ],
  "mixes": [
    {
      "id": "mix",
      "label": "مكس (اختر طعمين)",
      "pick": 2,
      "basePrice": 14,
      "flavorPrice": 7,
      "premiumFlavorPrice": 11,
      "flavorOptionIds": ["nutella", "lotus", "pistachio"]
    }
  ]
}
```

| Field | Type | Notes |
|---|---|---|
| `kind` | string | Must be `"flat-list"`. |
| `items` | array | Menu items. Each has `label`, `price`, optional `description`, optional `image`, `available`, optional `isPremiumMixFlavor`. Every item can be toggled available/unavailable. |
| `mixes` | array | Optional. Mix rules. Each has `id`, `label`, `pick` (how many items to select), `basePrice`, `flavorPrice` (per-item charge), `premiumFlavorPrice` (charge for items with `isPremiumMixFlavor`), `flavorOptionIds` (array of item labels or global flavor ids to choose from). |

---

## Complete Menu (19 Products)

### Category: ice-cream (2 products)

#### 1. بوظة كاسة (`cup`)
- **Flow:** container (cup/biscuit/takeaway) → size → flavor family → balls → extra-biscuit addon → qty
- **Containers:** كاسة (`cup`), بسكوت (`biscuit`), تيك اواي (`takeaway`) — all available
- **Flavor families:** classic, special, **mix**
- **selectionMode:** repeatable
- **hasExtraBiscuitAddon:** true (1₪ each)
- **Price table:**

| Container | Size | maxBalls | classic | special |
|---|---|---|---|---|
| كاسة | صغير | 1 | 2 | 4 |
| كاسة | وسط | 2 | 3 | 5 |
| كاسة | كبير | 3 | 5 | 7 |
| بسكوت | صغير | 1 | 2 | — *(no special price)* |
| بسكوت | وسط | 2 | 3 | 5 |
| بسكوت | كبير | 3 | 5 | 7 |
| تيك اواي | تيك اواي | 3 | 5 | 7 |

#### 2. بوظة عائلي (`family`)
- **Flow:** merged container+size selection (بلاستيك/فلين) → flavor family → balls → extra-biscuit addon → qty
- **Containers:** بلاستيك (available), فلين *(available: false — disabled but shown with "غير متوفر" badge)*
- **Flavor families:** classic, special, **mix** *(family shows explicit mix column)*
- **selectionMode:** repeatable
- **hasExtraBiscuitAddon:** true (1₪ each)
- **UI:** Merged container+size selection step displays options as cards:
  - 1/2 لتر بلاستيك
  - 1 لتر بلاستيك
  - 1/2 لتر فلين *(unavailable)*
  - 1 لتر فلين *(unavailable)*
- **Price table:**

| Container | Size | maxBalls | classic | special | mix |
|---|---|---|---|---|---|
| بلاستيك | 1/2 لتر | 8 | 14 | 18 | 16 |
| بلاستيك | 1 لتر | 12 | 28 | 35 | 32 |
| فلين *(disabled)* | 1/2 لتر | 8 | 16 | 20 | 18 |
| فلين *(disabled)* | 1 لتر | 12 | 31 | 38 | 35 |

---

### Category: brad (1 product)

#### 3. براد (`brad`)
- **Flow:** container (برادة flavor) → size → qty. **No ball-picking step.**
- **Containers:** ليمون, مانجا, مكس — all available
- **selectionMode:** N/A (no flavors, containers are the flavor choice)
- **Sizes & prices (single classic column ₪):** صغير 1 · وسط 2 · كبير 3
- **pricingLabel:** "أسعار البراد" (shared across all containers)

---

### Category: brad-boza (1 product)

#### 4. براد مع بوظة (`brad-boza`)
- **Flow:** container (برادة flavor) → size → **أضف بوظة** (ice-cream family) → balls → qty
- **Containers:** ليمون, مانجا, مكس — all available
- **Flavor families:** classic, special, **mix** *(for the ice-cream step)*
- **selectionMode:** toggle *(ice-cream picker allows each flavor ≤1)*
- **Sizes & base prices (₪):** صغير (2 balls) 1 · وسط (3 balls) 2 · كبير (4 balls) 3
- **iceCreamAddonPrices (added per-family):** classic +3 · special +5 · mix +4
- **Total unit price = base + addon.** Example: صغير + classic = 1 + 3 = 4₪.
- **pricingLabel:** "أسعار البراد" (shared)

---

### Category: cold-drinks (1 product)

#### 5. مشروبات باردة (`cold-drinks`) — favorites ✓, zoom ✓, notes ✓

| Item | ₪ | available |
|---|---|---|
| آيس كوفي كراميل | 8 | ✓ |
| آيس موكا | 8 | ✓ |
| سبانش لاتيه كراميل | 10 | ✓ |
| بوبا شيك كوفي/فراولة | 12 | ✓ |
| مياه صغيرة | 1 | ✓ |

---

### Category: hot-drinks (1 product)

#### 6. مشروبات ساخنة (`hot-drinks`) — favorites ✓, zoom ✓, notes ✓

| Item | ₪ |
|---|---|
| قهوة عربية | 5 |
| نسكافيه حار | 6 |
| شاي | 4 |
| هوت شوكولاتة | 8 |

---

### Category: juices (1 product)

#### 7. عصائر طبيعية (`juices`) — favorites ✓, zoom ✓, notes ✓

| Item | ₪ |
|---|---|
| فراولة | 5 |
| بلوليمونادا | 6 |
| مانجا | 7 |

---

### Category: corn (1 product)

#### 8. ذرة (`corn`) — favorites ✓, zoom ✓, notes ✓

| Item | ₪ |
|---|---|
| ذرة سادة | 5 |
| ذرة بالجبنة | 7 |
| ذرة بالشوكولاتة | 8 |

---

### Category: milkshake (1 product)

#### 9. ميلك شيك (`milkshake`) — favorites ✓, zoom ✓

**`addons[]` catalog** (per-unit extras, `IAddonOption`): صوص كراميل إضافي (3), صوص نوتيلا إضافي (4), بندق مبشور (4), قطع أوريو (3), بسكوت لوتس (4), كريمة مخفوقة (2).

| Item | ₪ | available |
|---|---|---|
| كلاسيك شوكولاته | 8 | ✓ |
| كلاسيك فانيلا | 8 | ✓ |
| كلاسيك فراولة | 8 | **✗** |
| كلاسيك كاراميل | 8 | ✓ |
| كلاسيك نسكافيه | 8 | ✓ |
| كلاسيك باروكا | 8 | **✗** |
| سبيشال نوتيلا | 10 | ✓ |
| سبيشال لوتس | 10 | ✓ |
| سبيشال كندر | 10 | ✓ |
| سبيشال أوريو | 10 | **✗** |
| سبيشال كت كات | 10 | ✓ |
| سبيشال فيتنس | 10 | ✓ |
| سبيشال شوفان | 10 | ✓ |
| سيرلاك (أطعم خاصة) | 8 | ✓ |
| اينشتاين (أطعم خاصة) | 9 | ✓ |
| بيستاشيو (أطعم خاصة) | 13 | ✓ |

> ⚠️ **Note:** Last 3 rows are milkshake-only variants, not the global ice-cream flavors. They happen to share a name in legacy code but are separate data.

---

### Category: kunafa (1 product)

#### 10. كنافة آيس كريم (`kunafa`) — favorites ✓, notes ✓

| Item | ₪ | available | isPremium |
|---|---|---|---|
| كنافة عربية | 8 | ✓ | — |
| كنافة لوتس | 8 | ✓ | — |
| كنافة نوتيلا | 8 | ✓ | — |
| كنافة بلوبيري | 8 | **✗** | — |
| كنافة دوندورما بيستاشيو | 12 | ✓ | **✓** |
| كنافة طاقة (كل خميس) | 12 | **✗** | — |

**Mix:** مكس (اختر طعمين) — pick 2, base 10, per-flavor 5, premium 8.

---

### Category: loqaimat (1 product)

#### 11. لقيمات (`loqaimat`) — favorites ✓, notes ✓

Same 6 flavors as kunafa with "لقيمة" prefix; identical prices/availability.

**Mixes:**
- مكس (اختر طعمين) — pick 2, base 10, per-flavor 5, premium 8
- سوبر مكس (اختر ثلاثة أطعمة) — pick 3, base 15, per-flavor 5, premium 8

---

### Categories: pancake / waffle / crepe / pizza (4 products)

All **inStoreOnly: true**, favorites ✓, notes ✓.

#### 12. بان كيك (`pancake`)

| Item | ₪ |
|---|---|
| نوتيلا | 11 |
| لوتس | 13 |
| بيستاشيو *(premium)* | 17 |

**Mixes:** مكس (pick 2, base 14, flavor 7, premium 11) · سوبر مكس (pick 3, base 18, flavor 6, premium 10)

#### 13. وافل (`waffle`)

| Item | ₪ |
|---|---|
| نوتيلا | 10 |
| لوتس | 12 |
| بيستاشيو *(premium)* | 14 |

**Mixes:** مكس (pick 2, base 14, flavor 7, premium 11) · سوبر مكس (pick 3, base 15, flavor 5, premium 9)

#### 14. كريب (`crepe`)

| Item | ₪ |
|---|---|
| نوتيلا | 9 |
| لوتس | 11 |
| بيستاشيو *(premium)* | 13 |

**Mixes:** مكس (pick 2, base 12, flavor 6, premium 10) · سوبر مكس (pick 3, base 15, flavor 5, premium 9)

#### 15. بيتزا جلاسيه (`pizza`)

| Item | ₪ |
|---|---|
| نوتيلا | 12 |
| لوتس | 14 |
| بيستاشيو *(premium)* | 16 |

**Mixes:** مكس (pick 2, base 16, flavor 8, premium 12) · سوبر مكس (pick 3, base 18, flavor 6, premium 10)

---

### Category: molten (1 product)

#### 16. مولتن كيك (`molten`) — inStoreOnly ✓, favorites ✓, notes ✓, **no mixes**

| Item | ₪ | Description |
|---|---|---|
| نوتيلا | 8 | كيك شوكولاتة دافئ بقلب سائل مع بوظة فانيلا |
| لوتس | 12 | كيك شوكولاتة دافئ بقلب سائل مع بوظة لوتس |
| بستاشيو | 12 | كيك شوكولاتة دافئ بقلب سائل مع بوظة بستاشيو |

---

### Category: desserts (3 products)

All favorites ✓, zoom ✓.

#### 17. براونيز (`brownie`)

| Item | ₪ |
|---|---|
| براونيز عادي | 8 |
| براونيز نوتيلا | 10 |
| براونيز لوتس | 10 |

#### 18. كوكيز (`cookies`)

| Item | ₪ |
|---|---|
| كوكيز نوتيلا | 8 |
| كوكيز لوتس | 10 |
| كوكيز بيستاشيو | 12 |
| كوكيز مكس | 10 |

#### 19. تشيز كيك (`cheesecake`)

| Item | ₪ |
|---|---|
| تشيز كيك فراولة | 12 |
| تشيز كيك لوتس | 14 |
| تشيز كيك بيستاشيو | 16 |
| تشيز كيك مكس | 14 |

---

## Dashboard / Admin Features

The backend **must** provide a dashboard allowing **real-time control** over:

1. **Categories** — view only (ids/labels are stable, but colors/icons/sortOrder can change if needed)
2. **Flavors** — toggle `available` on/off for each of 23 global flavors
3. **Products** — toggle `available` on/off, modify `hasNotes`/`hasFavorites`/`hasImageZoom`/`inStoreOnly`
4. **Items** (flat-list products) — toggle `available`, modify price, modify label/description
5. **Sizes** (builder products) — toggle `available`, modify `maxBalls`, modify prices per family
6. **Containers** (builder products) — toggle `available`, modify labels/pricingLabels
7. **Mixes** — modify pick count, base price, flavor prices, premium prices
8. **Extra-biscuit addon** — toggle `hasExtraBiscuitAddon` per product

### Key dashboard rule:
**If you toggle a product/item/flavor `available: false`, it still appears in the API response but is rendered greyed with a "غير متوفر / غير متاح" badge in the frontend.** It doesn't vanish; the frontend filters it post-fetch. This allows the backend to soft-delete without code changes.

---

## Error Handling & Edge Cases

1. **Invalid response (non-JSON, malformed data):** Frontend logs error, returns fake data. Never crashes.
2. **Product not found:** `GET /menu/products/{slug}` returns `null` (not 404 or empty object). Frontend renders a graceful "المنتج غير موجود" message.
3. **Missing optional fields:** Frontend treats missing fields as falsy/default. E.g., no `containerOptions` → no container step.
4. **Empty lists:** A category with zero products returns `[]` (not `null`). Frontend renders an empty state gracefully.
5. **Stale availability:** If a flavor is toggled unavailable mid-user-interaction, the picker still renders it (greyed). Real-time reactivity is nice-to-have, not required.

---

## Implementation Checklist for Laravel Backend

- [ ] Schema migration: tables for categories, flavors, products, items, sizes, containers, mixes, dashboard roles/permissions
- [ ] Endpoints: GET `/api/menu/categories`, `GET /api/menu/products`, `GET /api/menu/products/{slug}` (route-model-bind by `slug`, not PK), `GET /api/menu/addons`
- [ ] Products carry a distinct opaque `id` (PK) **and** a unique, stable `slug` (URL identifier) — don't reuse the PK as the slug
- [ ] Shared additions catalog (`GET /menu/addons` → `IAddonOption[]`, with prices, incl. biscuit) for the cart's per-unit additions flow; optional per-product `addons[]` override on the product payload
- [ ] Data validation: return only fields specified in this doc; drop/ignore unknown fields
- [ ] Dashboard UI: category/product/item/flavor CRUD with availability toggles
- [ ] Seed/fixture: load all 19 products, 23 flavors, 16 categories from this spec exactly
- [ ] Logging: endpoint hit counts, slow queries, malformed-row drops
- [ ] Testing: verify GET responses match exact JSON structure; test 404 behavior for unknown product ids; test availability toggles
- [ ] Documentation: generate Swagger/OpenAPI from this spec and share with mobile team

---

## Frontend will call these exact URLs

```
GET http://localhost:3000/api/menu/categories
GET http://localhost:3000/api/menu/products
GET http://localhost:3000/api/menu/products?category=ice-cream
GET http://localhost:3000/api/menu/products/cup
GET http://localhost:3000/api/menu/products/brad-boza
GET http://localhost:3000/api/menu/addons
```

(In production, replace `localhost:3000` with your backend domain.)

---

## Reference Files

- **Frontend type definitions:** `src/types/menu.types.ts` (TypeScript version of this spec)
- **API contract (OpenAPI):** `docs/swagger.yaml`
- **Fake data (current test data):** `src/data/fake-data/menu.ts`
