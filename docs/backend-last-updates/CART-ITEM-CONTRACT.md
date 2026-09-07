# عقد `items[]` في `POST /api/orders` — المرجع الكامل

> يجيب على تقرير الفرونت بتاريخ 2026-09-07. **السبب وُجد وأُصلح** — القسم 1.

## 1. سبب الـ422 (مُصلَح)

لم تكن المشكلة في شكل البيانات التي يرسلها الفرونت. كانت في الباك اند.

`StoreStorefrontOrderRequest::rules()` لم تكن تذكر `itemId` ولا `containerId` ولا
`sizeId`. و`$request->validated()` في Laravel **يُرجع المفاتيح المذكورة في
القواعد فقط** — فكل مُعرِّف غير مذكور كان يُحذف بصمت قبل أن يصل المسعّر.

وهذا يفسّر التناقض الذي أربككم بحق: الخادم يطلب `itemId`، وأنتم ترسلونه، وهو
يقول «اختر صنفاً من القائمة» — لأنه لم يستلمه أصلاً. الرسالة كانت صادقة والحقل
كان يُحذف قبلها بخطوة واحدة.

**الحقول المضافة الآن:** `itemId` · `sizeId` · `containerId` · `mixId` ·
`flavorIds[]` · `mixItemIds[]`

محاولاتكم 1 و3 و5 و6 كانت ستنجح لو وصلت.

## 2. الشكلان مقبولان — اختاروا واحداً

**أ. مُعرِّفات مسطّحة** (ما ترسلونه الآن):

```json
{ "productId": "<UUID>", "itemId": "arabian", "quantity": 2 }
```

**ب. `selections[]`** (ما يوثّقه `12-orders.md`):

```json
{
  "productId": "<UUID>",
  "selections": [{ "kind": "item", "id": "arabian", "qty": 1 }],
  "quantity": 2
}
```

كلاهما يمرّ على `CartItemNormalizer` ويصل المسعّر متطابقاً. المسطّح أوضح، و
`selections[]` أقرب للعقد المكتوب. **لا تخلطوا بينهما في نفس السطر** — المسطّح
يفوز عند التعارض.

## 3. مصدر كل مُعرِّف

**كل المُعرِّفات هي `slug`، لا UUID ولا اسم عربي.** الاستثناء الوحيد `productId`.

| الحقل | المصدر في `GET /api/menu/products/{slug}` | مثال |
|---|---|---|
| `productId` | `id` — **UUID** | `01M0R2S...` |
| `itemId` | `items[].id` | `arabian` |
| `sizeId` | `sizes[].id` | `plastic-half` |
| `containerId` | `containers[].id` | `plastic` |
| `mixId` | `mixes[].id` | `mix-3` |
| `flavorIds[]` | `flavors[].id` | `mango` |
| `mixItemIds[]` | `items[].id` | `arabian` |

## 4. ما يلزم لكل نوع منتج

### `flat-list` (لقيمات، ميلك شيك)

```json
{ "productId": "<UUID>", "itemId": "arabian", "quantity": 2 }
```

**أو** `mixId` بدل `itemId` للتشكيلات. أحدهما إلزامي.

### `builder` (بوظة كاسة)

```json
{
  "productId": "<UUID>",
  "containerId": "plastic",
  "sizeId": "plastic-half",
  "flavorIds": ["mango"],
  "quantity": 1
}
```

`flavorIds` **إلزامي وغير فارغ** — نكهة واحدة على الأقل، وإلا `اختر نكهة واحدة على الأقل`.

### الإضافات (كلا النوعين)

```json
"selections": [{ "kind": "addon", "id": "nuts", "qty": 1 }]
```

## 5. `container` و `type` — عناوين لا مُعرِّفات

`"container": "بلاستيك"` و `"type": "صغير"` **نصوص عرض**، تُستعمل احتياطاً فقط
حين لا يُرسل أي مُعرِّف. ويرفضها المسعّر إن كانت ملتبسة: منتجان قد يسمّيان مقاساً
«كبير»، والاسم ليس مُعرِّفاً ثابتاً.

**أرسلوا `containerId` و `sizeId` دائماً.** العناوين موجودة للتوافق مع نسخة قديمة
من المتجر، لا كمسار مدعوم.

## 6. كل الأسعار المُرسلة مُهمَلة

`unitPrice` · `addonTotal` · `subtotal` · `total` — يُقبل إرسالها ولا تُقرأ.
الخادم يسعّر السلة من الكتالوج، ويعيد الأرقام الحقيقية في رد `201`.

هذا ليس تشدّداً: السعر الذي يحدده العميل هو السعر الذي يختاره العميل.

## 7. أخطاء التحقق تسمّي الحقل

```json
{
  "message": "هذا الصنف غير موجود ضمن هذا المنتج",
  "errors": { "items.0.itemId": ["هذا الصنف غير موجود ضمن هذا المنتج"] }
}
```

المفتاح يحمل **رقم السطر** — `items.2.sizeId` يعني السطر الثالث في السلة.

| الرسالة | المعنى |
|---|---|
| `اختر صنفاً من القائمة` | لا `itemId` ولا `mixId` |
| `هذا الصنف غير موجود ضمن هذا المنتج` | الـslug ليس على هذا المنتج |
| `«س» غير متوفر حالياً` | موجود لكن `available = false` |
| `هذا الفرع غير موجود ضمن هذا المنتج` | `containerId` خاطئ |
| `اختر نكهة واحدة على الأقل` | `flavorIds` فارغ لمنتج builder |

## 8. `paypal` صار `palpay`

**PalPay شركة فلسطينية، وليست PayPal الأمريكية.** الاسم القديم كان يوجّه العميل
إلى خدمة أخرى تماماً.

`Order::PAYMENT_METHODS` صارت:

```
jawwal · jawwal-manual · palpay · cash · visa · wallet · bop
```

`paypal` **مرفوضة الآن**. هجرة تُحدّث الصفوف القائمة تلقائياً.

## 9. الحقل الجديد `paidAmount`

اختياري، للدفع النقدي فقط. ما ينوي العميل تسليمه؛ الفائض يصير رصيداً في محفظته
**لحظة استلام الكاشير للنقد**. التفاصيل في `COUNTER-AND-FINANCE.md` §7.

## 10. مثال كامل

```
POST /api/orders
Authorization: <token>
Content-Type: multipart/form-data
```

```
items = [
  { "productId": "01M0...", "itemId": "arabian", "quantity": 2 },
  { "productId": "01M1...", "containerId": "plastic", "sizeId": "plastic-half",
    "flavorIds": ["mango"],
    "selections": [{ "kind": "addon", "id": "nuts", "qty": 1 }],
    "quantity": 1 }
]
paymentMethod  = cash
deliveryMethod = pickup
paidAmount     = 100
```

`items` **نص JSON** داخل multipart — لأن `receiptImage` ملف حقيقي ولا يمكن تمرير
مصفوفة متداخلة في multipart.
