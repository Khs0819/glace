# 10 — عناوين التوصيل المحفوظة (CRUD)

**الأولوية:** عالية
**Endpoints:** `GET /addresses` · `POST /addresses` · `PUT /addresses/{id}` · `DELETE /addresses/{id}` · `POST /addresses/{id}/default` · `GET /addresses/delivery-zones`
**الحالة الحيّة (2026-08-31):** الفرونت بالكامل شغال على `localStorage` (`addressStore.ts`, zustand persist) — **مفيش أي endpoint حقيقي**، العناوين ما بتنقل بين الأجهزة ولا بتترجع لو مسح المستخدم بيانات المتصفح.

---

## شكل العنوان (`SavedAddress`)

```json
{
  "id": "addr_123",
  "type": "home",
  "label": "المنزل",
  "name": "أحمد علي",
  "phone": "0599123456",
  "city": "غزة",
  "zoneId": "rimal",
  "street": "شارع الجلاء",
  "landmark": "بجانب صيدلية النور",
  "location": { "lat": 31.5, "lng": 34.46 },
  "isDefault": true
}
```

| حقل | نوع | ملاحظات |
|---|---|---|
| `type` | `"home" \| "work" \| "other"` | `label` الافتراضي: المنزل/العمل، وبـ`other` لازم `label` حر مطلوب |
| `city` | string | الفرونت حالياً يثبّتها `"غزة"` دايماً (حقل غير قابل للتعديل بالفورم) — خزّنها كـstring عادي، مش لازم قائمة مدن |
| `zoneId` | string | مرجع لمنطقة توصيل — شوف تحت `GET /addresses/delivery-zones` لمصدر قائمة المناطق |
| `phone` | string | نمط `05XXXXXXXX` (يبدأ بـ05، 10 أرقام) — تحقق منه بالسيرفر كمان |
| `location` | `{lat,lng}?` | اختياري، دبوس GPS من خرائط أو من موقع الجهاز |
| `isDefault` | boolean | أول عنوان يضيفه المستخدم يصير افتراضي تلقائياً؛ باقي العناوين لازم `isDefault:false` |

---

## Endpoints

### `GET /addresses`
يرجع كل عناوين المستخدم الحالي (حسب التوكن)، الافتراضي أول واحد أو معلّم بـ`isDefault:true`.

### `POST /addresses`
Body = نفس شكل `SavedAddress` بدون `id`/`isDefault` (يتحسبوا بالسيرفر — أول عنوان = افتراضي تلقائي).

### `PUT /addresses/{id}`
نفس Body، تعديل كامل (مش partial).

### `DELETE /addresses/{id}`
لو كان هو الافتراضي واتحذف، السيرفر يعيّن عنوان تاني افتراضي تلقائياً (أو `null` لو ما بقي عناوين).

### `POST /addresses/{id}/default`
يشيل `isDefault` عن كل عناوين المستخدم الباقية ويحطها على هاد الواحد.

```json
// 200 على أي من الأربعة فوق (عدا GET اللي بيرجع array)
{ "address": { "id": "addr_123", "...": "..." } }

// 404
{ "message": "العنوان غير موجود" }
```

### `GET /addresses/delivery-zones`

31 منطقة بغزة حالياً hardcoded بـ`src/lib/deliveryZones.ts` برسوم توصيل ثابتة (0/10/15/20 ₪). الشكل المطلوب من السيرفر (نفس الشكل تماماً عشان يحل مكان الملف مباشرة):

```json
[
  { "id": "rimal", "name": "الرمال", "description": "حي الرمال", "fee": 10 },
  { "id": "shejaiya", "name": "الشجاعية", "fee": 15 }
]
```

`fee` هو رسم التوصيل بالشيكل لهاي المنطقة تحديداً. `description` اختياري. مش مربوط بمستخدم مسجّل دخول (بيستخدم بصفحة checkout حتى بدون تسجيل دخول)، فما بيحتاج توكن.

---

## معايير قبول

- [ ] العناوين مربوطة بحساب المستخدم (مش بالمتصفح) وبتظهر من أي جهاز بعد تسجيل الدخول
- [ ] أول عنوان يُضاف يصير `isDefault:true` تلقائياً
- [ ] حذف العنوان الافتراضي ينقل الافتراضي لعنوان تاني تلقائياً
- [ ] `phone` يترفض بـ422 لو مش بصيغة `05XXXXXXXX`
- [ ] `GET /addresses/delivery-zones` يرجع نفس شكل `src/lib/deliveryZones.ts` بالضبط (`id`/`name`/`fee`)
