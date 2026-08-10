# 🍦 Glace Menu Catalog & API Contract

**For Laravel Backend Developers**

> **⚠️ Read [`BACKEND_REQUIREMENTS.md`](./BACKEND_REQUIREMENTS.md) alongside this file.**
> That document is the current source of truth for what is still missing and for
> decisions taken after this catalog was written (inline `flavors[]`, stable
> `items[].id`, extra `available` levels). Where the two disagree, it wins.
> This file remains accurate for the **menu data itself** — every product,
> item, price and availability flag below was verified against the live API.

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
| `GET /api/menu/products/{slug}` | `useMenuProduct(slug)` | `IProduct \| null` | Single product for order page, looked up by **`slug`** (route-model-bind on the `slug` column, NOT the PK). **Returns `404` when the slug does not exist** — same as `/events/{id}`. (It currently answers `200 {}`; that is a bug.) |
| `GET /api/menu/addons` | `useMenuAddons()` | `IAddonOption[]` | Shared additions (إضافات) catalog with prices (sauces, nuts, biscuit…) for the cart's per-unit "تخصيص الإضافات" flow. A product may override via its own `addons`. |

> **Flavors have no endpoint of their own.** They are returned **inline** on the
> product detail payload as `IBuilderProduct.flavors[]` — see §IFlavorOption.
> Only `GET /menu/products/{slug}` carries them; the `/menu/products` list must
> omit them so the catalog is not repeated on every row.

All three endpoints:
1. The backend is the **only** source of menu data — the frontend holds no fake copy.
2. On network error, timeout, or invalid JSON: the query **rejects** and the UI shows an error state with a retry button. Nothing is invented.
3. A malformed row makes the whole response reject rather than being dropped silently — a backend regression must be visible, not hidden.
4. **Never returns `null` or empty object on success—always an array or typed object.** An empty array is a valid answer (a category with nothing in it) and is rendered as an empty state.

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
| `available` | boolean | true | Optional, defaults `true`. `false` hides the whole section from the menu (e.g. hot drinks in summer). Unlike items, a switched-off category is removed, not greyed — an empty section has nothing to show. |

---

### IFlavorOption
**Flavor balls for builder-template products (cup, family, brad-boza).**

Delivered **inline** on the product detail payload as `IBuilderProduct.flavors[]`
— there is no `/menu/flavors` endpoint. Scoped per product, so two builders may
offer different sets. `brad` has no flavor step and needs none.

**23 in the reference seed. Full add/edit/delete from the dashboard** — the
frontend assumes no fixed ids.

```json
{
  "id": "pistachio",
  "nameAr": "بيستاشيو",
  "nameEn": "Pistachio",
  "image": "https://cdn.glace.ps/flavors/pistachio.jpg",
  "family": "special",
  "available": true,
  "isPremiumMixFlavor": true
}
```

| Field | Type | Example | Notes |
|---|---|---|---|
| `id` | string | "pistachio" | Stable slug, unique within the product. |
| `nameAr` | string | "بيستاشيو" | Arabic label on flavor-ball picker. |
| `nameEn` | string | "Pistachio" | English label (optional, for backend logs). |
| `image` | string (URL) | "https://..." | Flavor photo. Can be static or CDN. |
| `family` | enum | "special" | **Exactly one of `classic`, `special`, `stevia`** — the frontend rejects anything else and the whole response fails. Stevia folds into the classic picker. ⚠️ **`mix` and `super-mix` are NOT flavor families** — see the note below. |
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
  "image": "https://cdn.glace.ps/products/cup.jpg",
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
      "image": "https://cdn.glace.ps/containers/cup.jpg",
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
| `sizes` | array | Size rows. Each has `id`, `label`, `maxBalls` (flavor-ball count; 0 if no picker), `containerId` (optional, filters to one container), `prices` (grid of `{flavorFamily, price}`), and optional `available`. |
| `sizes[].available` | boolean | Optional, defaults `true`. `false` greys the size with a "غير متوفر" badge. Lets one size be stopped **independently of its container** — Family merges container+size into a single step, so "1 لتر فلين" must be stoppable while "1/2 لتر فلين" stays orderable. |
| `hasExtraBiscuitAddon` | boolean | If true, the order page shows an extra-biscuit counter. **The price, label and max quantity come from the `extra-biscuit` entry in `GET /menu/addons`** (currently 3₪, maxQty 10) — never hardcoded. |
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
      "id": "nutella",
      "label": "نوتيلا",
      "price": 11,
      "description": "كيك دافئ مع بوظة",
      "image": "https://cdn.glace.ps/items/nutella-pancake.jpg",
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
      "itemIds": ["nutella", "lotus", "pistachio"]
    }
  ]
}
```

| Field | Type | Notes |
|---|---|---|
| `kind` | string | Must be `"flat-list"`. |
| `items` | array | Menu items. Each has a stable **`id`** (see below), `label`, `price`, optional `description`, optional `image`, `available`, optional `isPremiumMixFlavor`. Every item can be toggled available/unavailable. |
| `items[].id` | string | **Stable, unique within the product** (e.g. `nutella`). Mixes reference items by this id, so renaming `label` from the dashboard never breaks a mix. Never changes → 🔒 seed-time only. |
| `mixes` | array | Optional. Mix rules. Each has `id`, `label`, `pick` (how many items to select), `basePrice`, `flavorPrice` (per-item charge), `premiumFlavorPrice` (charge for items with `isPremiumMixFlavor`), **`itemIds`**, and optional `available`. |
| `mixes[].available` | boolean | Optional, defaults `true`. `false` hides this mix rule for the product. |
| `mixes[].itemIds` | string[] | Ids of the `items[]` this mix can be built from — **`IProductVariant.id` values on the same product**. NOT flavor ids, NOT labels. (Was `flavorOptionIds` holding Arabic labels; label matching broke on every rename.) |

---

## Item vs Flavor Ball — read this before modelling

Two different entities are easy to confuse because they share names in Arabic.

| | `items[]` — **item** | `flavors[]` — **flavor ball** |
|---|---|---|
| Type | `IProductVariant` | `IFlavorOption` |
| Example | عصير فراولة · ميلك شيك نوتيلا | كرة بوظة فراولة |
| Price | ✅ **has its own `price`** | ❌ **no price** — comes from the size |
| Bought | added to the cart directly | placed inside a cup/tub |
| Quantity | any | capped by `maxBalls` |
| Lives on | every `flat-list` product | `builder` products only |

The same word can be both: **فراولة** is an item on `juices` (5₪) *and* a flavor
ball on `cup` (priced by size). They are separate rows in separate tables.

### Which products get what

| Product | `flavors[]` | `mixes[]` |
|---|---|---|
| `cup` · `family` · `brad-boza` | ✅ | ❌ |
| `brad` | ❌ *(no flavor step — `flavorFamilies` is empty)* | ❌ |
| **`milkshake`** | ❌ | ❌ — flat items only |
| `pizza` · `crepe` · `waffle` · `pancake` · `loqaimat` | ❌ | ✅ mix + super-mix |
| `kunafa` | ❌ | ✅ mix only |
| all other flat-list | ❌ | ❌ |

> **milkshake**: "كلاسيك"/"سبيشال" are part of the item **label**
> (`"كلاسيك شوكولاته"`), not a field. Do not send it `flavors[]`.

### `mix` and `super-mix` are not flavors

Three separate concepts:

| Concept | Field | Values | Belongs to |
|---|---|---|---|
| Flavor family | `flavors[].family` | `classic` · `special` · `stevia` | the flavor |
| Pricing tier | `flavorFamilies` | `classic` · `special` · `mix` | a `builder` product |
| Mix rule | `mixes[]` | `mix` · `super-mix` | a `flat-list` product |

🔴 **No flavor ever has `family: "mix"`.** `mix` in `flavorFamilies` is a pricing
mode meaning "combine classic and special at a special rate" — the balls shown
are the same ordinary flavors. `super-mix` exists only as an `IMixRule` on
flat-list products and has nothing to do with `IFlavorOption`.

---

## Complete Menu (19 Products)

### Category: ice-cream (2 products)

#### 1. بوظة كاسة (`cup`)
- **Flow:** container (cup/biscuit/takeaway) → size → flavor family → balls → extra-biscuit addon → qty
- **Containers:** كاسة (`cup`), بسكوت (`biscuit`), تيك اواي (`takeaway`) — all available
- **Flavor families:** classic, special, **mix**
- **selectionMode:** repeatable
- **hasExtraBiscuitAddon:** true (price from `/menu/addons` → `extra-biscuit`, currently 3₪)
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
- **Containers:** `plastic` → بلاستيك (available), `foam` → فلين *(available: false — disabled but shown with "غير متوفر" badge)*
- **Flavor families:** classic, special, **mix** *(family shows explicit mix column)*
- **selectionMode:** repeatable
- **hasExtraBiscuitAddon:** true (price from `/menu/addons` → `extra-biscuit`, currently 3₪)
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

> **The display structure is fixed in the frontend; only data varies.**
> The step-by-step scenario for each product (بوظة كاسة, بوظة عائلي, براد,
> ميلك شيك…) is a hard-coded template. The dashboard fills in its values — it
> must **never** be able to add, remove or reorder a step. See the
> 🔒 fixed / 🔄 variable table in `BACKEND_REQUIREMENTS.md` §4.

### 🔄 Editable from the dashboard

1. **Categories** — `available`, label, colors, `sortOrder`
2. **Flavors** — full add / edit / delete, plus `available` and image
3. **Products** — `available`, name, description, image, `sortOrder`
4. **Items** (flat-list) — `available`, price, label, description, image; add/remove items
5. **Sizes** (builder) — `available`, prices per family, `maxBalls` *(changes the ball limit — edit with care)*
6. **Containers** (builder) — `available`, label, `pricingLabel`, image
7. **Mixes** — `available`, base price, flavor prices, premium prices
8. **Addons** — price, label, `available`, `maxQty` (incl. `extra-biscuit`)

### 🔒 NOT editable — changing these breaks the order flow

`kind` · `slug` · `categoryId` · `selectionMode` · `flavorFamilies` ·
`includesIceCreamStep` · `hasExtraBiscuitAddon` · `hasNotes` · `hasFavorites` ·
`hasImageZoom` · `inStoreOnly` · `mixes[].pick` · `category.icon` ·
and every id: `items[].id`, `flavors[].id`, `sizes[].id`, `containerOptions[].id`,
`mixes[].id`, `sizes[].containerId`

> Only **`flat-list`** products may be created from the dashboard. The four
> `builder` products (cup, family, brad, brad-boza) each have a hand-built
> scenario and cannot be added without frontend work.

### Key dashboard rule:
**If you toggle a product/item/flavor `available: false`, it still appears in the API response but is rendered greyed with a "غير متوفر / غير متاح" badge in the frontend.** It doesn't vanish; the frontend filters it post-fetch. This allows the backend to soft-delete without code changes.

---

## Error Handling & Edge Cases

1. **Invalid response (non-JSON, malformed data):** the query rejects and the UI shows "تعذّر التحميل" with a retry button. Never crashes, never substitutes fake data.
2. **Product not found:** `GET /menu/products/{slug}` returns **`404`**. Frontend renders a graceful "المنتج غير موجود" message. A 404 is distinguished from a network failure — the latter shows the retry state instead.
3. **Missing optional fields:** Frontend treats missing fields as falsy/default. E.g., no `containerOptions` → no container step.
4. **Empty lists:** A category with zero products returns `[]` (not `null`). Frontend renders an empty state gracefully.
5. **Stale availability:** If a flavor is toggled unavailable mid-user-interaction, the picker still renders it (greyed). Real-time reactivity is nice-to-have, not required.

---

## Implementation Checklist for Laravel Backend

**Endpoints** (four — flavors are inline, not a fifth route)
- [ ] `GET /api/menu/categories`
- [ ] `GET /api/menu/products` — **without** `flavors[]`
- [ ] `GET /api/menu/products/{slug}` — route-model-bind by `slug` (not PK); **carries `flavors[]`**; `404` when missing
- [ ] `GET /api/menu/addons`

**Schema**
- [ ] Tables: categories, flavors, products, items, sizes, containers, mixes, addons
- [ ] `product_flavor` join table so one flavor can serve several products
- [ ] Products carry an opaque `id` (PK) **and** a unique stable `slug` — don't reuse the PK as the slug
- [ ] **Every item carries a stable `id`**, unique within its product; `mixes[].itemIds` reference it
- [ ] `available` columns on: products, items, flavors, containers, **sizes**, **mixes**, **categories**, addons

**Images**
- [ ] Dashboard upload returning real URLs for: products, **items** (all 69), flavors, containers
- [ ] No `example.com` host anywhere in seed data

**Behaviour**
- [ ] Return only the fields in this doc; reject/ignore unknown fields
- [ ] An empty category returns `[]`, never `null`
- [ ] Unavailable rows are still returned (greyed by the frontend), except categories which are hidden

**Dashboard**
- [ ] CRUD with availability toggles for everything in the 🔄 list above
- [ ] The 🔒 list must be seed-time only — not exposed as editable fields
- [ ] Only `flat-list` products can be created; `builder` products are fixed at four

**Seed / QA**
- [ ] Load 19 products, 69 items (with ids), 23 flavors, 16 categories, 7 addons from `docs/reference/fake-data/menu.ts`
- [ ] Verify every `mixes[].itemIds` entry resolves to an `items[].id` on the same product
- [ ] Test `404` for unknown slug, availability toggles, and the `?category=` filter

---

## Frontend will call these exact URLs

```
GET {API_BASE}/menu/categories
GET {API_BASE}/menu/products
GET {API_BASE}/menu/products?category=ice-cream
GET {API_BASE}/menu/products/cup          ← carries flavors[]
GET {API_BASE}/menu/products/brad-boza    ← carries flavors[]
GET {API_BASE}/menu/addons
```

`{API_BASE}` is `https://glace-bzjj.onrender.com/api` on staging. The category
filter parameter is **`category`** — any other name is ignored and the full list
is returned.

---

## Reference Files

- **Frontend type definitions:** `src/types/menu.types.ts` (TypeScript version of this spec)
- **API contract (OpenAPI):** `docs/swagger.yaml`
- **Reference seed data:** `docs/reference/fake-data/menu.ts` — all 19 products, 69 items (each with a stable `id`), and 23 flavors, ready to seed from. Reference only: excluded from the build, imported by nothing.
- **Current gaps & requirements:** `docs/BACKEND_REQUIREMENTS.md`
