# المتبقي للباك اند — Glace API

**تاريخ:** 2026-08-13  
**الحكم:** القبول مرفوض لحد ما البنود الأربعة تحت تتقفل  
**API:** `http://acw348d983gr8x01lb5myd3x.64.176.172.179.sslip.io/api`  
**داشبورد:** `…/admin`  
**عقد:** [`swagger.yaml`](./swagger.yaml)

`audit-api.mjs` الآن: **1 فشل** (`items[].image` = 0/69) · **1 تحذير جوهري** (`events[].images` دايمًا `[]`)

---

## الناقص فقط

| # | الملف | المطلوب |
|---|---|---|
| 1 | [`01-items-image.md`](./01-items-image.md) | CRUD أصناف flat-list + تعبئة `items[].image` (69) |
| 2 | [`05-builder-sizes.md`](./05-builder-sizes.md) | CRUD أنواع/أحجام/أسعار + `sizes[].image` + `iceCreamAddonPrices` |
| 3 | [`07-flat-list-mixes.md`](./07-flat-list-mixes.md) | CRUD مكسات flat-list |
| 4 | [`04-event-gallery.md`](./04-event-gallery.md) | حقل معرض `images[]` في Edit Event |

---

## تحقق بعد التنفيذ

```bash
API=http://acw348d983gr8x01lb5myd3x.64.176.172.179.sslip.io/api
node scripts/audit-api.mjs "$API"

# 01 — لازم missing=0
curl -s "$API/menu/products/loqaimat" | jq '.items[] | {id, price, image}'

# 05
curl -s "$API/menu/products/family" | jq '.sizes[] | {id, image, prices}'

# 07 — بعد CRUD: غيّر سعر من الأدمن وتأكد انعكس
curl -s "$API/menu/products/pancake" | jq '.mixes'

# 04 — بعد رفع معرض من الأدمن
curl -s "$API/events/3" | jq '{listImage, images}'
```

**القبول:** audit بدون فشل · كل تذكرة فوق متعلّمة · الستورفرونت يعرض صور الأصناف/الأحجام/معرض الفعالية بدون placeholders.

---

## لقطات النواقص

[`images/`](./images/)

- [`loqaimat.png`](./images/loqaimat.png) — سعر ظاهر · مكان صورة الصنف فاضي  
- [`dashboard-settings.png`](./images/dashboard-settings.png) — flat-list بدون تبويب أصناف/مكسات  
- [`family-dashboard.png`](./images/family-dashboard.png) — Builder بدون أنواع/أحجام/أسعار  
- [`family-storefront.png`](./images/family-storefront.png) — أماكن صور الأحجام فاضية  
- [`brad-storefront.png`](./images/brad-storefront.png) — أحجام/أسعار ظاهرة بدون تحكم أدمن  
- [`dashboard-event.png`](./images/dashboard-event.png) — فعالية: بطاقة فقط · مفيش معرض  

---

## ملاحظة

الفرونت بدون fallback لداتا المنيو. الصور = URL كامل فقط (`Storage::disk('public')->url(...)`).
