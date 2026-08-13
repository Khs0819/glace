# 07 — مكسات flat-list: CRUD من الداشبورد

**الأولوية:** عالية  
**الحالة الحيّة (2026-08-13):** `mixes[]` موجودة في الـAPI (seed) · **مفيش** واجهة إدارة في Filament

منتجات فيها مكسات: `pancake` · `waffle` · `crepe` · `pizza` · `loqaimat` (mix+super-mix) · `kunafa` (mix فقط)

---

## المطلوب

على Edit منتج flat-list → Repeater/Relation **المكسات**:

| حقل | ملاحظة |
|---|---|
| `id` | ثابت بعد الإنشاء (`mix` / `super-mix`) |
| `label` | يظهر للزبون |
| `pick` | عدد الأطعمة |
| `basePrice` · `flavorPrice` · `premiumFlavorPrice` | أسعار |
| `itemIds` | multi-select على `items[].id` لنفس المنتج |
| `available` | إيقاف بدون حذف |

الداشبورد حاليًا: معلومات أساسية + إعدادات عرض فقط — **بدون مكسات ولا أصناف**:

![بدون مكسات](./images/dashboard-settings.png)
![إعدادات العرض فقط](./images/dashboard-product-presentaion-settings.png)

```bash
curl -s "$API/menu/products/pancake" | jq '.mixes[] | {id, pick, basePrice, itemIds, available}'
```

---

## معايير قبول

- [ ] إضافة / تعديل / إيقاف / حذف مكس من Filament
- [ ] تغيير سعر أو `itemIds` ينعكس فورًا على `GET /menu/products/{slug}`
- [ ] `available: false` يخفي المكس من صفحة الطلب
