# 01 — أصناف flat-list: صور + أسعار من الداشبورد (`items[]`)

**الأولوية:** عالية  
**Endpoints:** `GET /menu/products` · `GET /menu/products/{slug}`  
**Schema:** `IProductVariant` في `docs/swagger.yaml`  
**تاريخ التحقق:** 2026-08-11 · **إعادة تحقق:** 2026-08-12

---

## قرار المنتج

الأدمن يقدر من **Filament** يدير كل صف في `items[]` لكل منتج `kind: "flat-list"`:

| حقل | مطلوب من الأدمن |
|---|---|
| `label` | ✅ |
| `price` | ✅ |
| `available` | ✅ |
| **`image`** | ✅ رفع صورة |
| `isPremiumMixFlavor` | ✅ إن لزم |

ينطبق على الميلك شيك واللقيمات والبان كيك والذرة والمشروبات و… **كل الـ15 منتج / 69 صنف**.

الفرونت جاهز: السعر والصورة يظهروا جنب الاسم في `/menu/order/{slug}` بمجرد ما الحقول توصل.

---

## الحكم (حاسم)

| | في الـAPI | في الداشبورد |
|---|---|---|
| `items[].price` | ✅ بيرجع (من seed) | ❌ **مفيش مكان** لتعديل سعر الصنف |
| `items[].image` | ⚠️ الحقل موجود = **`null` دائمًا** (0/69) | ❌ **مفيش مكان** لرفع صورة الصنف |
| `items[].label` / `available` | ✅ بيرجعوا | ❌ **مفيش مكان** لإدارتهم |

يعني: البيانات متسيّدة في الـAPI، والأدمن **ما يقدرش** يغيّر سعر ولا يرفع صورة لأي صنف flat-list.

---

## دليل من الستورفرونت + الـAPI (لقيمات)

على `/menu/order/loqaimat` السعر ظاهر (مثلاً **8**) ومكان الصورة فاضي لأن `image: null`:

![لقيمات — السعر ظاهر · مكان الصورة فاضي](./images/loqaimat.png)

عيّنة حية من `GET /menu/products/loqaimat`:

```json
{
  "slug": "loqaimat",
  "kind": "flat-list",
  "name": "لقيمات",
  "image": null,
  "items": [
    {
      "id": "arabian",
      "label": "لقيمة عربية",
      "price": 8,
      "available": true,
      "image": null
    },
    {
      "id": "lotus",
      "label": "لقيمة لوتس",
      "price": 8,
      "available": true,
      "image": null
    },
    {
      "id": "dondurma-pistachio",
      "label": "لقيمة دوندورما بيستاشيو",
      "price": 12,
      "available": true,
      "image": null,
      "isPremiumMixFlavor": true
    }
  ],
  "mixes": [ /* موجودة في الـAPI أيضًا — شوف تذكرة 07 */ ]
}
```

- `price` شغال على الفرونت ← جاي من الـAPI  
- `image: null` ← مكان فاضي جنب الاسم  
- **ولا السعر ولا الصورة ليهم حقل في الأدمن**

---

## دليل من الداشبورد (2026-08-12)

شاشة تعديل منتج flat-list فيها تابين فقط — **مفيش** قائمة أصناف ولا سعر صنف ولا رفع صورة:

![تعديل منتج flat-list — معلومات أساسية فقط](./images/dashboard-settings.png)

![إعدادات العرض — سويتشات واجهة فقط، بدون أصناف](./images/dashboard-product-presentaion-settings.png)

**المطلوب إضافته:** تبويب/ريليشن «الأصناف» مع:

- تعديل `label` · `price` · `available` · `isPremiumMixFlavor`
- FileUpload لـ`image` (URL كامل عبر `Storage::url`)

---

## الحالة الحيّة — صور الأصناف (0 على الكل)

| slug | عناصر | عندها `image` |
|---|---:|---:|
| `milkshake` | 16 | **0** |
| `pancake` | 3 | **0** |
| `waffle` | 3 | **0** |
| `crepe` | 3 | **0** |
| `pizza` | 3 | **0** |
| `molten` | 3 | **0** |
| `brownie` | 3 | **0** |
| `cookies` | 4 | **0** |
| `cheesecake` | 4 | **0** |
| `kunafa` | 6 | **0** |
| `loqaimat` | 6 | **0** |
| `corn` | 3 | **0** |
| `juices` | 3 | **0** |
| `hot-drinks` | 4 | **0** |
| `cold-drinks` | 5 | **0** |
| **المجموع** | **69** | **0** |

صورة المنتج الأب (`product.image`) موجودة لمعظم المنتجات — **مش كافية**. المطلوب صورة **لكل صنف** في القائمة.

---

## المطلوب في الـAPI

كل عنصر في `items[]`:

| الحقل | مطلوب؟ | ملاحظة |
|---|---|---|
| `id` | نعم | موجود ✓ |
| `label` | نعم | موجود ✓ |
| `price` | نعم | موجود ✓ |
| `available` | نعم | موجود ✓ |
| **`image`** | **نعم** | ❌ ناقص على 69/69 |
| `description` | لا | اختياري |

في الـSwagger:

```yaml
IProductVariant:
  required: [id, label, price, available, image]
```

مثال:

```json
{
  "slug": "milkshake",
  "kind": "flat-list",
  "image": "products/milkshake-hero.png",
  "items": [
    {
      "id": "chocolate",
      "label": "كلاسيك شوكولاته",
      "price": 8,
      "available": true,
      "image": "products/items/milkshake-chocolate.png"
    },
    {
      "id": "nutella",
      "label": "سبيشال نوتيلا",
      "price": 10,
      "available": true,
      "image": "products/items/milkshake-nutella.png"
    }
  ]
}
```

مقبول: مسار storage أو URL مطلق حقيقي (مش `example.com`).

---

## شغل الأدمن (Filament) — مرة واحدة تغطي الكل

1. تبويب/Relation Manager **«الأصناف»** على منتج flat-list — مش بس صورة المنتج الأب ولا إعدادات العرض.
2. لكل صنف يمكن للأدمن تعديل: `label` · **`price`** · `available` · `isPremiumMixFlavor` · رفع **`image`**.
3. نفس الواجهة لكل منتج flat-list (الـ15 أعلاه).
4. الـAPI Resource يسلّسل كل الحقول في القائمة والتفصيل.

الأدمن يرفع الصور ويعدّل الأسعار؛ الباك يرجّعها؛ الفرونت يعرضها تلقائياً.

---

## سلوك الفرونت (تم — لا شغل إضافي)

- `items[].price` → ظاهر جنب الاسم (شغال من الـAPI حتى بدون داشبورد)
- `items[].image` → thumbnail جنب الاسم؛ بدون صورة = مكان فاضي (زي `loqaimat.png`)
- مودال المكس: صورة + اسم + سعر الوحدة داخل المكس

```tsx
{item.image && <Image src={resolveMenuImageSrc(item.image)} ... />}
```

---

## معايير القبول

- [ ] من الداشبورد: CRUD أصناف + تعديل **`price`** + رفع **`image`**
- [ ] تغيير سعر صنف من الأدمن يظهر فورًا على `/menu/order/{slug}`
- [ ] **كل** عنصر عنده `image` غير فاضي (69/69)
- [ ] مفيش `cdn.example.com` أو host ميت
- [ ] `/menu/order/loqaimat` تظهر thumbnail لكل صف مش مكان فاضي
- [ ] مودال المكس يظهر صورة لكل صنف جنب الاسم والسعر
- [ ] `scripts/audit-api.mjs` بند `(3) items[].image` يمرّ بنجاح

```bash
API=http://acw348d983gr8x01lb5myd3x.64.176.172.179.sslip.io/api

curl -s "$API/menu/products/loqaimat" | jq '.items[] | {id, label, price, image}'

# لازم يطبع missing=0 لكل منتج
for slug in milkshake pancake waffle crepe pizza molten brownie cookies cheesecake kunafa loqaimat corn juices hot-drinks cold-drinks; do
  missing=$(curl -s "$API/menu/products/$slug" | jq '[.items[] | select(.image == null or .image == "")] | length')
  echo "$slug: missing=$missing"
done
```
