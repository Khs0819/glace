# 02 — نكهات الـBuilder: `available: false` + `family.flavors`

**الأولوية:** عاجلة (تقفل الطلب)  
**Endpoints:** `GET /menu/products/{slug}` فقط  
**ملاحظة:** `GET /menu/products` (القائمة) **لازم تفضل بدون** `flavors[]`

---

## أ) ابعت `"available": false` صراحةً

### الحالة الحالية (2026-08-11)

| المنتج | `flavors` | المشكلة |
|---|---|---|
| `cup` | 23 نكهة | `mango` و`flora` **بدون** حقل `available` |
| `brad-boza` | 23 نكهة | نفس المشكلة |
| `family` | ❌ مش موجودة | شوف القسم ب |
| `brad` | مش مطلوبة | ✅ (منتج بدون خطوة نكهات) |

الفرونت **يرفض الرد كله** لو نكهة ناقصها `available` → شاشة خطأ على صفحة الطلب.

### السبب المرجّح

`array_filter()` بدون callback، أو شرط يضيف الحقل بس لما يكون `true`.

### المطلوب

```json
{
  "id": "mango",
  "nameAr": "مانجا",
  "nameEn": "Mango",
  "family": "classic",
  "image": "flavors/mango.png",
  "available": false
}
```

**غلط:** حذف الحقل لما القيمة `false`.  
**صح:** إرسال `false` دائماً (ومعه `true` لما متاح).

### PHP — توجّه

```php
// غلط — بيرمي القيم الـfalsy
array_filter([
    'available' => $flavor->available,
]);

// صح
return [
    'id' => $flavor->id,
    'nameAr' => $flavor->name_ar,
    'nameEn' => $flavor->name_en,
    'family' => $flavor->family,
    'image' => $flavor->image_path, // أو Storage::url(...)
    'available' => (bool) $flavor->available, // حتى لو false
];
```

### معايير قبول أ

- [ ] `cup` و`brad-boza`: كل عنصر في `flavors[]` فيه `available` (boolean)
- [ ] النكهات الموقوفة ترجع `"available": false` مش تختفي من المصفوفة (إلا إذا سياسة المنتج تحذفها بالكامل — حالياً الفرونت يتوقعها مع `false` ويظهرها معطّلة)
- [ ] صفحة طلب `cup` و`brad-boza` تفتح بدون شاشة خطأ

```bash
curl -s "$API/menu/products/cup" | jq '[.flavors[] | select(has("available")|not)] | length'
# المتوقع: 0
```

---

## ب) `family` لازم ترجّع `flavors[]`

### الحالة الحالية

`GET /menu/products/family` → **مفيش مفتاح `flavors`** → خطوة الأطعام فاضية/مقفولة.

### المطلوب

نفس شكل `cup`: مصفوفة `IFlavorOption[]` داخل تفاصيل المنتج.

```json
{
  "slug": "family",
  "kind": "builder",
  "flavors": [
    {
      "id": "pistachio",
      "nameAr": "فستق",
      "nameEn": "Pistachio",
      "family": "special",
      "image": "flavors/pistachio.png",
      "available": true
    }
  ]
}
```

الحقول المطلوبة لكل نكهة (`IFlavorOption.required`):

`id` · `nameAr` · `nameEn` · `image` · `family` · `available`

`family` ∈ `classic` | `special` | `stevia`

### شغل الداتا / الأدمن

1. ربط نكهات بـ`family` عبر pivot `product_flavor` (أو المكافئ).
2. التأكد إن الـResource للـdetail يحمّل العلاقة ويسلسلها.
3. القائمة `GET /menu/products` تبقى **بدون** `flavors` (عشان الحجم).

### معايير قبول ب

- [ ] `GET /menu/products/family` فيه `flavors` مصفوفة غير فارغة
- [ ] كل نكهة تعدّي عقد `IFlavorOption`
- [ ] `GET /menu/products` ما بيرجّعش `flavors` على أي صف
- [ ] صفحة طلب العائلي توصل لخطوة النكهات وفيها عناصر

```bash
curl -s "$API/menu/products/family" | jq '.flavors | length'
# المتوقع: > 0

curl -s "$API/menu/products" | jq '[.[] | select(has("flavors"))] | length'
# المتوقع: 0
```
