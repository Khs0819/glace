# 05 — Builder: أنواع + أحجام + أسعار + صور أحجام

**الأولوية:** عالية  
**منتجات:** `cup` · `family` · `brad` · `brad-boza`  
**الحالة الحيّة (2026-08-13):** البيانات **موجودة كـseed في الـAPI** · **مفيش أي CRUD في Filament** · `sizes[].image` = null

---

## المطلوب في Filament (تبويب Builder)

حاليًا التبويب فيه فقط: وضع اختيار النكهات · عائلات · `pricingLabel` · سويتش «أضف بوظة».

**لازم يتضاف:**

| شيء | الحقل |
|---|---|
| CRUD الأنواع | `containerOptions[]` |
| CRUD الأحجام | `sizes[]` |
| أسعار كل حجم | `sizes[].prices[]` (`flavorFamily` + `price`) |
| صورة صف الحجم | `sizes[].image` → URL كامل |
| أسعار إضافة البوظة (brad-boza فقط) | `iceCreamAddonPrices` |

![داشبورد بدون أنواع/أحجام](./images/family-dashboard.png)

![عائلي — أماكن صور الأحجام فاضية](./images/family-storefront.png)

![براد — أحجام ظاهرة بدون تحكم أدمن](./images/brad-storefront.png)

---

## شكل الـAPI المطلوب بعد الرفع

```json
{
  "id": "plastic-half",
  "label": "1/2 لتر",
  "containerId": "plastic",
  "maxBalls": 8,
  "available": true,
  "image": "https://host/storage/sizes/….png",
  "prices": [
    { "flavorFamily": "classic", "price": 14 },
    { "flavorFamily": "special", "price": 18 },
    { "flavorFamily": "mix", "price": 16 }
  ]
}
```

أمثلة أنواع:  
`cup` → كاسة/بسكوت/تيك اواي · `family` → بلاستيك/فلين · `brad`/`brad-boza` → ليمون/مانجا/مكس

```bash
curl -s "$API/menu/products/cup" | jq '.sizes[] | {id, image, prices}'
curl -s "$API/menu/products/brad-boza" | jq '.iceCreamAddonPrices'
```

---

## معايير قبول

- [ ] الأدمن يقدر يضيف/يعدّل/يحذف نوع وحجم ويغيّر الأسعار
- [ ] `sizes[].image` يرجع URL بعد الرفع
- [ ] `iceCreamAddonPrices` قابلة للتعديل من الأدمن
- [ ] التغيير يظهر فورًا على `/menu/order/cup` و`/family` و`/brad` و`/brad-boza`
