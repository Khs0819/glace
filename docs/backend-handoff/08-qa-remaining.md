# 08 — نواقص بعد مراجعة الـAPI والداشبورد (QA)

**الأولوية:** عاجل — حجب قبول نهائي  
**تاريخ المراجعة:** 2026-08-12  
**API الحي:** `http://acw348d983gr8x01lb5myd3x.64.176.172.179.sslip.io/api`  
**داشبورد:** `http://acw348d983gr8x01lb5myd3x.64.176.172.179.sslip.io/admin`  
**العقد:** [`swagger.yaml`](./swagger.yaml) · التذاكر [`01`](./01-items-image.md)–[`07`](./07-flat-list-mixes.md)  
**أداة التحقق:** `node scripts/audit-api.mjs <API_BASE>`

---

## الحكم

الباك رفع نسخة جديدة وقال إنها جاهزة. المراجعة على الـAPI الحي + Filament أثبتت:

| نتيجة `audit-api.mjs` | القيمة |
|---|---|
| نجح | 27 |
| تحذير | 2 |
| **فشل** | **2** |

**القبول النهائي مرفوض** لحد ما البنود أدناه تتقفل.

---

## ملخص سريع — حالة التذاكر الأصلية بعد الرفع

| # | التذكرة | API | داشبورد | الحكم |
|---|---|---|---|---|
| 01 | [`01-items-image`](./01-items-image.md) | ❌ `items[].image` = **0/69** · ✅ `price` بيرجع من API | ❌ **مفيش مكان** لسعر/صورة الصنف | **مفتوح** |
| 02 | [`02-builder-flavors`](./02-builder-flavors.md) | ✅ `available:false` محفوظ · `family` فيه 23 نكهة | ✅ سويتش «متوفرة» + صورة نكهة | **مغلق** |
| 03 | [`03-media-and-cleanup`](./03-media-and-cleanup.md) | ✅ صور النكهات 23/23 absolute | ✅ حقل صورة النكهة | **مغلق** (جزئيًا: باقي تنظيف الإضافات) |
| 04 | [`04-home-and-events`](./04-home-and-events.md) | ⚠️ URL كامل ✅ · paragraphs ✅ · limit 10 ✅ · `listImage` 3/10 · **`images` دايمًا `[]`** · هوم `image: null` | ❌ صورة بطاقة فقط · **مفيش مكان لـ`images[]`** | **مفتوح جزئيًا** |
| 05 | [`05-builder-sizes`](./05-builder-sizes.md) | ⚠️ `cup`+`family`+`brad`: أنواع/أحجام/أسعار في API · ❌ `sizes[].image` مش راجع | ❌ **مفيش تحكم** نوع/حجم/سعر على الكل | **مفتوح** |
| 06 | [`06-brad-boza-media`](./06-brad-boza-media.md) | ✅ `pricingLabel` · بدون `secondaryImage` · `iceCreamAddonPrices` موجودة | ⚠️ `pricingLabel` قابل للتعديل · **بدون** تعديل أسعار الإضافة | **مغلق API / ناقص داشبورد** |
| 07 | [`07-flat-list-mixes`](./07-flat-list-mixes.md) | ✅ `mixes[]` + أسعار موجودة (seed) | ❌ مفيش CRUD مكسات | **مفتوح داشبورد** |

---

## أ) نواقص الـAPI (لسه موجودة على الحي)

### أ-1 — أصناف flat-list: صور + أسعار — تذكرة 01

| | API | داشبورد |
|---|---|---|
| `items[].price` | ✅ بيرجع (seed) — مثال loqaimat arabian = **8** | ❌ **مفيش مكان** لتعديل السعر |
| `items[].image` | ⚠️ موجود = **`null` على 69/69** | ❌ **مفيش مكان** لرفع الصورة |
| `items[].label` / `available` | ✅ | ❌ مفيش إدارة أصناف |

دليل ستورفرونت — السعر ظاهر ومكان الصورة فاضي:

![لقيمات — price من الـAPI · image null](./images/loqaimat.png)

```json
{
  "id": "arabian",
  "label": "لقيمة عربية",
  "price": 8,
  "available": true,
  "image": null
}
```

**المطلوب:** CRUD أصناف في Filament (سعر + صورة + توفّر) + تعبئة صور الـ69 صنف.

---

### أ-2 — أنواع/أحجام/أسعار Builder + صور الأحجام — تذكرة 05

ينطبق على **كل** builder. أمثلة حية:

#### `cup` / بوظة كاسة

الأنواع + الأحجام + الأسعار **بيرجعوا مع بعض في الـAPI** — ومفيش لهم تحكم في الداشبورد:

| | API | داشبورد |
|---|---|---|
| اختر النوع (`containerOptions`: كاسة/بسكوت/تيك اواي) | ✅ | ❌ **مفيش تحكم** |
| اختر الحجم (`sizes`: صغير/وسط/كبير) | ✅ | ❌ **مفيش تحكم** |
| الأسعار (`sizes[].prices` — مثلاً cup-small classic = 2) | ✅ | ❌ **مفيش تحكم** |
| `sizes[].image` | ❌ مش راجع | ❌ مفيش رفع |

```bash
curl -s "$API/menu/products/cup" | jq '{containers: .containerOptions, sizes: [.sizes[] | {id, label, containerId, prices, image}]}'
```

#### `family` / بوظة عائلي

| | API | داشبورد |
|---|---|---|
| `containerOptions` (بلاستيك/فلين) | ✅ | ❌ مفيش تحكم |
| `sizes` (1/2 لتر · 1 لتر) | ✅ | ❌ مفيش تحكم |
| `sizes[].prices` (classic نصف بلاستيك = 14) | ✅ | ❌ مفيش تحكم |
| `sizes[].image` | ❌ مش راجع | ❌ مفيش رفع |

![عائلي — أماكن صور الأحجام فاضية على الفرونت](./images/family-storefront.png)

#### `brad` / براد

الأحجام والأسعار ظاهرة على الفرونت (`/menu/order/brad`) من الـAPI — **مفيش تحكم** في الداشبورد:

| | API | داشبورد |
|---|---|---|
| اختر النوع (ليمون/مانجا/مكس) | ✅ | ❌ مفيش تحكم |
| اختر الحجم (صغير/وسط/كبير) | ✅ | ❌ **مفيش تحكم** |
| الأسعار (1 / 2 / 3 ₪) | ✅ `sizes[].prices` | ❌ **مفيش تحكم** |

![براد — أحجام وأسعار بدون تحكم أدمن](./images/brad-storefront.png)

```bash
curl -s "$API/menu/products/brad" | jq '{containers: .containerOptions, sizes: [.sizes[] | {id, label, prices}]}'
```

**المطلوب:** CRUD أنواع+أحجام+أسعار من Filament لكل builder (`cup` · `family` · `brad` · `brad-boza`) + إرجاع `sizes[].image` كـURL كامل.

---

### أ-3 — فعاليات ناقصة ميديا — تذكرة 04

| بند | الحالة الحيّة (2026-08-12) |
|---|---|
| شكل URL للصور المرفوعة | ✅ absolute على `/storage/...` (مثال: `…/storage/events/01KZRF6….png`) |
| `GET /events` عدد العناصر | ✅ ≥ 10 (مع `perPage`) |
| `listImage` | ⚠️ **3/10** فقط (ids 1, 2, 3) |
| `images[]` معرض تفاصيل الفعالية الواحدة | ❌ **مفيش مكان في الداشبورد** · الحقل دايمًا `[]` على كل الفعاليات |
| `home.events.items[].image` | ❌ كلها `null` (حتى لما `/events` عنده `listImage`) |
| `about.paragraphs` | ✅ `string[]` |

```bash
curl -s "$API/events?perPage=20" | jq '[.items[] | {id, listImage: (.listImage != null), gallery: (.images|length)}]'
curl -s "$API/home" | jq '.events.items[] | {id, image}'
curl -s "$API/events/3" | jq '{listImage, images}'
```

> بند «المسار النسبي» **مقفول**. المتبقي = تعبئة/ربط الصور الناقصة + معرض.

---

### أ-4 — Hero ناقص جزئيًا — تذكرة 04

سلايد واحد فقط فيه `manImg` كامل؛ باقي السلايدات `manImg: null`.  
رفع الصور الناقصة من الأدمن (سلايدر الرئيسية) أو إخفاء السلايد الفاضي من الـAPI.

---

### أ-5 — إضافات مكررة في `/menu/addons`

`audit-api.mjs` فشل على تكرار الـ`id`:

`ms-caramel` · `pk-nutella` · `ms-nutella` · `pk-nuts` · `ms-nuts` · `ms-oreo` · `ms-lotus` · `ms-cream`

| مقياس | القيمة |
|---|---:|
| صفوف في الـAPI | 23 |
| IDs فريدة | 15 |

**المطلوب:** unique constraint على `id`/`slug` + حذف المكررات من الداتابيس.

---

### أ-6 — ملاحظة أسعار Builder/mixes (مش نقص API)

أسعار الـbuilder والمكسات **موجودة** في الـAPI الحي:

| المصدر | مثال حي |
|---|---|
| `items[].price` | loqaimat arabian = 8 ← **موجود في API · مش في الداشبورد** (شوف أ-1) |
| `sizes[].prices` | cup-small classic = 2 |
| `iceCreamAddonPrices` | classic = 3 · special = 5 · mix = 4 |
| `mixes[].basePrice` / `flavorPrice` / `premiumFlavorPrice` | loqaimat mix base = 10 |

المشكلة المشتركة = **إدارة الأسعار من الداشبورد** (أصناف → أ-1/ب-1 · أحجام → ب-3 · مكسات → ب-2).

---

## ب) نواقص الداشبورد (Filament)

تم الدخول ومراجعة الشاشات: المنتجات · النكهات · الفعاليات · الإضافات المشتركة.  
لقطات الدليل في [`images/`](./images/).

### ب-1 — أصناف flat-list (سعر + صورة) — تذكرة 01

**مفيش مكان في الداشبورد** لإدارة `items[]` — لا السعر ولا الصورة.

صفحة تعديل منتج flat-list فيها فقط: المعلومات الأساسية · إعدادات العرض.

![flat-list — معلومات أساسية بدون أصناف](./images/dashboard-settings.png)

![flat-list — إعدادات العرض بدون أصناف](./images/dashboard-product-presentaion-settings.png)

بينما الـAPI بيرجّع الأصناف كاملة (سعر موجود · صورة `null`) والفرونت بيعرض السعر ومكان فاضي للصورة:

![لقيمات على الستورفرونت](./images/loqaimat.png)

**مش موجود:**

- [ ] قائمة/ريليشن للأصناف (`items[]`)
- [ ] تعديل **`price`** لكل صنف
- [ ] حقل رفع **`image`** لكل صنف
- [ ] تعديل `label` / `available` / `isPremiumMixFlavor`

---

### ب-2 — مكسات flat-list — تذكرة 07

الـAPI بيرجّع `mixes[]` (pancake / kunafa / …) من seed، لكن الأدمن **ما بيقدر** — نفس الشاشتين أعلاه مفيش تبويب مكسات:

- [ ] إضافة / تعديل / حذف مكس
- [ ] تعديل `pick` · `basePrice` · `flavorPrice` · `premiumFlavorPrice`
- [ ] ربط `itemIds`
- [ ] تعطيل مكس بـ`available`

---

### ب-3 — أنواع + أحجام + أسعار Builder — تذكرة 05

على **بوظة كاسة** (`cup`) و**بوظة عائلي** (`family`) و**براد** (`brad`) وباقي الـbuilder — **مفيش تحكم** في:

- «اختر النوع» (`containerOptions`)
- «اختر الحجم» (`sizes`) — بما فيها صغير/وسط/كبير للبراد
- الأسعار (`sizes[].prices`) — بما فيها 1/2/3 ₪ للبراد

رغم إنهم بيرجعوا مع بعض من الـAPI ويظهروا على الفرونت.

![براد — أحجام وأسعار من الـAPI](./images/brad-storefront.png)

![داشبورد عائلي — بدون أنواع/أحجام/أسعار (نفس الشكل على cup و brad)](./images/family-dashboard.png)

تبويب «إعدادات Builder» فيه فقط: وضع الاختيار · عائلات النكهات · `pricingLabel` · سويتش «أضف بوظة».

**مش موجود (مطلوب):**

- [ ] CRUD `containerOptions[]` (كاسة · بسكوت · تيك اواي / بلاستيك · فلين / ليمون · مانجا · مكس / …)
- [ ] CRUD `sizes[]` (صغير · وسط · كبير / 1/2 لتر · 1 لتر + `containerId` + `maxBalls`)
- [ ] تعديل **`sizes[].prices[]`** من الأدمن (`cup` · `family` · **`brad`** · `brad-boza`)
- [ ] رفع **`sizes[].image`** وإرجاعها في الـAPI
- [ ] تعديل `iceCreamAddonPrices` لـ`brad-boza`

أي تغيير من الأدمن لازم يظهر فورًا على `/menu/order/cup` و`/menu/order/family` و`/menu/order/brad` بدون deploy فرونت.

---

### ب-4 — معرض صور الفعالية الواحدة `images[]` — تذكرة 04

**لا يوجد مكان في الداشبورد** لرفع صور المعرض الخاصة بالفعالية الواحدة.

شاشة Edit Event فيها فقط: العنوان · التاريخ · **صورة البطاقة** (`listImage`) · الوصف — **بدون أي حقل لـ`images[]`**:

![تعديل فعالية — صورة البطاقة فقط · مفيش معرض](./images/dashboard-event.png)

لذلك الـAPI بيرجّع دائمًا مصفوفة فاضية، مثال حي:

```json
{
  "id": 6,
  "title": "أجواء العيد مع جلاسيه غير",
  "date": "11/06/2020",
  "description": "كل عام وانتم بخير بحلول عيد الفطر المبارك احتفالنا معكم بالعيد أجمل . أهلا وسهلاُ بكم في جلاسيه فرع الاتصالات تفضلوا عنا , هناك عروض مميزة بانتظاركم",
  "listImage": null,
  "images": []
}
```

نفس الشكل على **كل** الـ10 فعاليات: `images: []` مهما كان فيه `listImage` أو لأ.

**المطلوب:**

- [ ] إضافة حقل رفع متعدد في Filament باسم واضح (مثل «معرض صور الفعالية») — **منفصل** عن «صورة البطاقة»
- [ ] تخزين + إرجاع URLs كاملة في `images[]`
- [ ] تعبئة `listImage` للفعاليات 4–10 (يدوي عبر صورة البطاقة) + ربط `home.events.items[].image`

---

### ب-5 — تنظيف الإضافات المكررة

شاشة «الإضافات المشتركة» تعرض نفس الـslug مرتين (مثال: `pk-nutella` صفّين، `ms-caramel` صفّين).

- [ ] حذف الصفوف المكررة
- [ ] منع إنشاء `id` مكرر من الفورم

---

## ج) اللي اتعمل (لا تعيدوه)

| بند | دليل |
|---|---|
| نكهات `available: false` تفضل في الـpayload | flora / mango على cup |
| `family.flavors` موجودة (23) | `GET /menu/products/family` |
| صور النكهات absolute | `/storage/flavors/...` |
| `about.paragraphs` = `string[]` | `GET /home` |
| روابط ميديا absolute بدون `example.com` | audit بند (2) ✅ — مثال فعاليات: `…/storage/events/01KZRF6….png` |
| `home.events.items` ≥ 10 | audit بند (9) |
| brad-boza بدون `secondaryImage` | مفيش المفتاح في الـpayload |
| `pricingLabel: "أسعار البراد"` | `GET /menu/products/brad-boza` |
| بيانات أسعار الأحجام/المكسات/الأصناف في الـAPI | شوف أ-6 |
| بند URL النسبي (handoff 04 §1) | ✅ مقفول |

---

## معايير القبول النهائية

### API

- [ ] `items[].image` غير فاضي على **69/69**
- [ ] `sizes[].image` مدعوم ويرجع URL لما الأدمن يرفع
- [ ] كل فعالية عندها `listImage` غير فاضي (أو مخفية من القوائم)
- [ ] `events[].images[]` قابلة للتعبئة وترجع URLs كاملة
- [ ] `home.events.items[].image` مش `null` لما البطاقة موجودة على `/events`
- [ ] `/menu/addons` بدون أي `id` مكرر
- [ ] `node scripts/audit-api.mjs <API>` → **0 فشل**

### داشبورد

- [ ] CRUD أصناف flat-list + رفع صورة صنف
- [ ] CRUD مكسات flat-list (كل حقول التذكرة 07)
- [ ] CRUD أنواع + أحجام + أسعار + صورة حجم (التذكرة 05)
- [ ] تعديل `iceCreamAddonPrices` لـbrad-boza
- [ ] رفع معرض صور للفعالية
- [ ] مفيش إضافات مكررة في الجدول

### ستورفرونت (تحقق يدوي بعد الباك)

- [ ] `/menu/order/corn` و`/menu/order/milkshake` تظهر thumbnail لكل صف
- [ ] مودال مكس (waffle/pancake) صورة + اسم + سعر
- [ ] `/menu/order/family` صور صفوف الأحجام بعد الرفع
- [ ] `/events/{id}` معرض شغال
- [ ] الهوم بدون placeholders مكسورة للفعاليات

---

## أوامر تحقق سريعة

```bash
API=http://acw348d983gr8x01lb5myd3x.64.176.172.179.sslip.io/api

node scripts/audit-api.mjs "$API"

# 01 — صور الأصناف (لازم missing=0 لكل slug)
for slug in milkshake pancake waffle crepe pizza molten brownie cookies cheesecake kunafa loqaimat corn juices hot-drinks cold-drinks; do
  missing=$(curl -s "$API/menu/products/$slug" | jq '[.items[] | select(.image == null or .image == "")] | length')
  echo "$slug: missing=$missing"
done

# 05 — صور أحجام العائلي
curl -s "$API/menu/products/family" | jq '.sizes[] | {id, label, image, prices}'

# 07 — مكسات (بيانات موجودة؛ CRUD من الأدمن هو الناقص)
curl -s "$API/menu/products/pancake" | jq '.mixes[] | {id, pick, basePrice, flavorPrice, premiumFlavorPrice, itemIds}'

# 04 — فعاليات
curl -s "$API/events?perPage=20" | jq '[.items[] | {id, hasList: (.listImage!=null), gallery:(.images|length)}]'
curl -s "$API/home" | jq '[.events.items[] | {id, listImage}]'

# إضافات مكررة (لازم [])
curl -s "$API/menu/addons" | jq 'group_by(.id) | map(select(length>1) | .[0].id)'
```

---

## ترتيب تنفيذ مقترح للباك

1. **01** صور الأصناف + حقل الأدمن (أعلى أثر بصري على الستورفرونت)
2. **05** CRUD أنواع/أحجام/أسعار + صورة حجم + `iceCreamAddonPrices`
3. **07** CRUD مكسات flat-list
4. **04** `listImage` للناقص + معرض `images[]` + تضمين في `/home`
5. تنظيف **addons** المكررة
6. تعبئة سلايدات الـhero الناقصة (أو إخفاؤها)

---

## ملاحظات للباك

- الفرونت **ما عندوش fallback** لداتا المنيو. رد ناقص/غلط = شاشة خطأ للزبون.
- الصور = URL كامل فقط (`Storage::disk('public')->url(...)`). المسار النسبي مش عقد.
- `cdn.example.com` مرفوض ويُعامل كـ«مفيش صورة».
- تفاصيل العقد والأمثلة في التذاكر 01–07؛ هذا الملف = **قائمة المتبقي بعد QA 2026-08-12** فقط.
