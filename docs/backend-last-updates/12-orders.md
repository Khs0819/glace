# 12 — الطلبات: إنشاء + تتبع + مناطق التوصيل والجدولة

**الأولوية:** عالية جداً (بدون هاد ما في طلب حقيقي بيوصل لأي حد)
**Endpoints:** `POST /orders` · `GET /orders` · `GET /orders/{id}` · `POST /orders/jawwal/send-code` · `POST /orders/{id}/cancel` · `POST /orders/{id}/receipt` · `POST /orders/{id}/received`
**الحالة الحيّة (2026-08-31):** الفرونت بالكامل بيعتمد على الباك اند الحقيقي — **ما في fallback ولا mock ولا localStorage** (`orderStore.ts` صار zustand عادي بدون `persist`، مجرد مرآة/cache للطلبات المجلوبة، مش مصدر حقيقة). لسا محتاج الـendpoints تتعمل فعلياً عالسيرفر — كل استدعاء حالياً بيفشل لحد ما توصل.

> مناطق التوصيل (`GET /addresses/delivery-zones`) انتقلت لـ[`10-addresses.md`](./10-addresses.md) — منطقياً جزء من نظام العناوين، مش الطلبات.

---

## 2. جدولة الوقت — منطق فرونت بالكامل، لا يحتاج API الآن

توليد الأيام/الأوقات المتاحة (3 أيام قدام، فواصل 15 دقيقة، 11ص–11م، بدون جمعة) محسوب بالكامل بـ`src/lib/scheduling.ts`. **مفيش endpoint مطلوب هلق** — بس لو صار في ساعات عمل تتغيّر من لوحة التحكم مستقبلاً، هاد المنطق لازم ينتقل للسيرفر وقتها. تجاهلوا هاد الجزء حالياً.

---

## 3. قيود التوصيل على أصناف معيّنة

بعض المنتجات ممنوعة من التوصيل (منطق حالياً بالفرونت فقط بـ`src/lib/deliveryRestrictions.ts`، مبني على `slug`/`inStoreOnly`/حجم):
- منتج `gelatodome` ممنوع توصيل دايماً.
- أي منتج `inStoreOnly: true` (موجود أصلاً بـ`IProductBase`, شوف `swagger.yaml`).
- `brad`/`brad-boza`/`cup` بالحجم الصغير/المتوسط ممنوعين توصيل (بس مسموح استلام).

**المطلوب:** بدل ما هاد المنطق يضل مكرر بالفرونت، `POST /orders` (تحت) لازم يرفض الطلب لو فيه صنف ممنوع من التوصيل مع `deliveryMethod: "delivery"` — رجّع 422 بدل قبول طلب غير صالح. مش ضروري تعديل شكل استجابة المنتجات هلق، بس تأكيد السيرفر ما بيقبل طلب توصيل فيه صنف ممنوع.

---

## 4. إنشاء طلب — `POST /orders`

```ts
type PaymentMethod = "jawwal" | "jawwal-manual" | "paypal" | "cash" | "visa" | "wallet" | "bop";
type DeliveryMethod = "delivery" | "pickup" | "dine-in";
```

الفرونت بيبعت هاد الطلب كـ`multipart/form-data` (مش JSON) عشان `receiptImage` ملف حقيقي — `items` بالتحديد بيوصل كـ**نص JSON** جوا حقل واحد (`FormData.append("items", JSON.stringify(items))`), والباقي حقول عادية:

```
// Request (multipart/form-data fields)
items: "[{\"productId\":\"b7f1c2a4-...\",\"name\":\"بوظة كاسة\",\"image\":\"https://.../cup.jpg\",\"type\":\"صغير\",\"container\":\"كاسة\",\"selections\":[{\"kind\":\"addon\",\"id\":\"extra-caramel\",\"label\":\"صوص كراميل\",\"qty\":1,\"unitPrice\":3}],\"addonTotal\":3,\"unitPrice\":15,\"quantity\":2,\"flatSelections\":[{\"kind\":\"addon\",\"id\":\"extra-biscuit\",\"label\":\"بسكوت إضافي\",\"qty\":4,\"unitPrice\":3}],\"flatAddonTotal\":12}]"
couponCode: "GLACE10"
paymentMethod: "jawwal-manual"
deliveryMethod: "delivery"
addressId: "addr_123"
pickupTime: (omitted)
receiptImage: <binary file>
receiptNote: (omitted)
jawwalPhone: (omitted — only for paymentMethod=jawwal)
jawwalCode: (omitted — only for paymentMethod=jawwal)
```

```json
// 201
{
  "id": "ORD-M3K2A1",
  "items": [ /* نفس الأصناف، بأسعار محسوبة من السيرفر */ ],
  "subtotal": 33,
  "discount": 10,
  "total": 23,
  "paymentMethod": "jawwal-manual",
  "deliveryMethod": "delivery",
  "address": { "name": "أحمد علي", "phone": "0599123456", "city": "غزة", "area": "الرمال", "street": "شارع الجلاء", "landmark": null, "note": null },
  "status": "قيد المراجعة",
  "createdAt": "2026-08-31T10:00:00Z",
  "receiptImage": "https://.../storage/receipts/....png",
  "preparationTime": 15
}
```

**نقاط حرجة:**

- **السيرفر يحسب الأسعار من جديد** (`unitPrice`, `addonTotal`, `subtotal`, `discount`, `total`) — ما يثق بأي رقم سعر جاي من الفرونت، فقط بـ`productId`/`selections ids`/`quantity`/`couponCode`. هاد أهم بند أمني بكل الملف.
- الفرونت يرسل `addressId` (مرجع لعنوان محفوظ من [`10-addresses.md`](./10-addresses.md))، مش عنوان حر — السيرفر يجيب العنوان ويحوّله لقطة `address` جوا الطلب (بما فيها `area` = اسم المنطقة `zoneId` وقت الطلب، حتى لو المستخدم عدّل/حذف العنوان بعدين).
- `receiptImage` ملف حقيقي (multipart) — يرجع URL مخزّن بالاستجابة، مش base64. مطلوب فقط لما `paymentMethod` من ["jawwal-manual", "paypal", "bop"] (= `RECEIPT_METHODS`).
- لو `receiptImage` غير موجود، `receiptNote` (نص حر) بديل مقبول لنفس الطرق.
- لما `paymentMethod` = `"jawwal"` (آلي)، الفرونت قبلها بيستدعي `POST /orders/jawwal/send-code` (تحت) ويبعت `jawwalPhone`/`jawwalCode` هون — السيرفر يتحقق من الكود قبل إنشاء الطلب، كود خاطئ/منتهي = 422 وما ينخلق طلب.
- الحالة الأولى دايماً `"قيد المراجعة"` بغض النظر عن طريقة الدفع.
- `preparationTime` (5-30 دقيقة) و`estimatedDeliveryTime` (10-25 دقيقة، توصيل فقط) يحددهم السيرفر/الموظف — مش الفرونت.

---

## 4.1 بدء دفع جوال باي الآلي — `POST /orders/jawwal/send-code`

نفس تكامل JawwalPay المستخدم بشحن المحفظة (`docs/backend-handoff/14-wallet.md`) — السيرفر يطلب من JawwalPay يبعت كود تأكيد لـ`phone` بقيمة `amount` (إجمالي الطلب). الكود الراجع بينبعت مع `jawwalPhone`/`jawwalCode` بالـ`POST /orders` اللي بعده مباشرة — الفرونت ما بيحكي مع JawwalPay مباشرة، وما في طلب موجود لسا بهاي اللحظة.

```json
// Request
{ "phone": "0599123456", "amount": 42.5 }
// 200
{ "sent": true }
```

لازم rate-limit عالرقم لمنع إساءة استخدام الـSMS (429 لو تكرر كتير).

---

## 5. حالات الطلب (`OrderStatus`) — لكل طريقة توصيل خطوات مختلفة

```ts
type OrderStatus =
  | "قيد المراجعة" | "جاري التحضير" | "جاهز للاستلام" | "في الطريق"
  | "تم التسليم" | "تم الاستلام" | "ملغي" | "مسترد";
```

خطوات التتبع بالواجهة (من `src/lib/orderStatusSteps.ts`) — يهمّ الباك اند إنه ما يرسل حالة غير منطقية لطريقة توصيل معيّنة:

| `deliveryMethod` | تسلسل الحالات المتوقع |
|---|---|
| `dine-in` | قيد المراجعة ← تم التسليم |
| `pickup` | قيد المراجعة ← جاري التحضير ← جاهز للاستلام ← تم التسليم |
| `delivery` | قيد المراجعة ← جاري التحضير ← في الطريق ← تم الاستلام |

`"ملغي"` و`"مسترد"` ممكن يصيروا من أي حالة نشطة. `isOrderFinal(status)` = تم التسليم/تم الاستلام/ملغي/مسترد (الطلب خلص التعامل النشط فيه).

طلب توصيل قيد "في الطريق" ممكن يحمل:
```json
{
  "driver": { "id": "d1", "name": "محمود الأحمد", "phone": "0599876543", "company": "توصيل فلسطين" },
  "driverAssignedAt": "2026-08-31T09:40:00Z"
}
```

---

## 6. باقي الـendpoints

### `GET /orders`
كل طلبات المستخدم الحالي، الأحدث أولاً — **مقسّم صفحات (paginated)**، نفس شكل `WalletTransactionsResponse` (`docs/backend-handoff/14-wallet.md`):
```
?page=1&perPage=20
```
```json
// 200
{ "items": [ /* Order[] */ ], "total": 47, "page": 1, "perPage": 20, "totalPages": 3 }
```

### `GET /orders/{id}`
تفاصيل طلب واحد (تستخدم بصفحة تتبع الطلب `OrderStatusClientPage`).

### `POST /orders/{id}/cancel`
```json
// Request
{ "reason": "غيرت رأيي" }
// 200 — يحوّل الحالة "ملغي" ويخزّن السبب
{ "order": { "id": "ORD-M3K2A1", "status": "ملغي", "cancelReason": "غيرت رأيي" } }
```
لازم يُرفض (409 مثلاً) لو الطلب أصلاً بحالة نهائية (`isOrderFinal`).

**الاسترداد مش تلقائي/فوري** — الإلغاء بس يحوّل الحالة لـ`"ملغي"`، والمبلغ يضل "قيد المراجعة" لحد ما فريق الدعم/لوحة التحكم يتحقق منه ويعالجه — التحويل الفعلي لحالة `"مسترد"` (وإضافة المبلغ لمحفظة الزبون) خطوة منفصلة لاحقة، خارج نطاق هاد الملف. واجهة الفرونت (`OrderStatusClientPage`) بتعكس هيك بالضبط — نص "قيد المراجعة"، مش وعد باسترداد فوري.

### `POST /orders/{id}/receipt`
لإعادة رفع/تعديل إيصال التحويل لطلب موجود (`RECEIPT_METHODS` فقط) — نفس فكرة الرفع بالإنشاء، multipart، ما يغيّر حالة الطلب.
```json
{ "receiptImage": "<file>", "receiptNote": null }
```

### `POST /orders/{id}/received`
يستخدمه الزبون لتأكيد استلام طلب توصيل — يحوّل الحالة لـ`"تم الاستلام"` ويسجّل `receivedAt`.

### `POST /orders/{id}/email-summary`
ميزة اختيارية بصفحة تتبع الطلب ("يوصلك ملخص الطلب على إيميلك؟") — يرسل ملخص الطلب (الأصناف، المجاميع، الحالة) للإيميل المُدخل. مش مرتبط بإيميل الحساب، الزبون بيقدر يكتب أي إيميل. ما بيغيّر حالة الطلب ولا بيشترط حالة معينة.
```json
// Request
{ "email": "customer@example.com" }
// 200
{ "sent": true }
```

---

## معايير قبول

- [ ] `POST /orders` يحسب كل الأسعار بالسيرفر، ما يثق بأي سعر من الفرونت
- [ ] طلب `delivery` فيه صنف ممنوع توصيل يترفض بـ422
- [ ] الإيصال (`receiptImage`) رفع ملف حقيقي (multipart) يرجع URL، مش base64 مخزّن كنص
- [ ] `paymentMethod: "jawwal"` يتحقق من `jawwalPhone`/`jawwalCode` عبر JawwalPay قبل إنشاء الطلب — كود خاطئ/منتهي = 422 وما ينخلق طلب
- [ ] `GET /orders` مقسّم صفحات (`items/total/page/perPage/totalPages`)
- [ ] تسلسل الحالات محترم حسب `deliveryMethod` (جدول فوق)
- [ ] إلغاء طلب بحالة نهائية مرفوض
