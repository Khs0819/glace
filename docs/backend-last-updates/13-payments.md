# 13 — طرق الدفع: تحويل بنكي يدوي + رفع إيصال

**الأولوية:** عالية
**Endpoints:** `GET /payment-accounts` · (يستخدم `POST /orders/{id}/receipt` من [`12-orders.md`](./12-orders.md))
**الحالة الحيّة (2026-08-31):** حسابات الاستلام (QR/رقم حساب) hardcoded بـ`src/lib/merchantPaymentAccounts.ts` — بيانات تجريبية بأسماء/أرقام شكلها حقيقي، **لازم تتأكدوا هاي مش أرقام حقيقية فعلاً قبل ما يشوفها زبون حقيقي**.

---

## طرق الدفع المدعومة

```ts
type PaymentMethod = "jawwal" | "jawwal-manual" | "paypal" | "cash" | "visa" | "wallet" | "bop";

// طرق بدون تكامل تحويل داخل التطبيق — الزبون يدفع خارجياً ويرفع إثبات
const RECEIPT_METHODS: PaymentMethod[] = ["jawwal-manual", "paypal", "bop"];
```

- `cash`, `visa`: بالمحل فقط (in-store only بالفرونت).
- `wallet`: خصم من رصيد المحفظة الداخلي — شوف [`14-wallet.md`](./14-wallet.md).
- `jawwal`: تحويل "آلي" (تدفق إرسال/إدخال كود) — لسا مش متكامل فعلياً مع أي API خارجي، شوف ملاحظة تحت.
- `jawwal-manual`, `paypal`, `bop`: تحويل يدوي + رفع إيصال (`RECEIPT_METHODS`).

## `GET /payment-accounts`

الشكل المطلوب (يطابق `MerchantPaymentAccount` بالفرونت تماماً):

```json
[
  {
    "method": "bop",
    "qrImage": "https://.../storage/payment-accounts/bop-qr.png",
    "holderName": "شركة جلاسيه الأمير",
    "bankName": "بنك فلسطين",
    "primaryLabel": "رقم الحساب",
    "primaryValue": "123456789",
    "secondaryLabel": "IBAN",
    "secondaryValue": "PS00PALS000000000000123456789"
  },
  {
    "method": "jawwal-manual",
    "qrImage": "https://.../storage/payment-accounts/jawwal-qr.png",
    "holderName": "جلاسيه الأمير",
    "primaryLabel": "رقم جوال باي",
    "primaryValue": "0599123456"
  }
]
```

`bankName` بس للطرق البنكية (`bop`)، يترك فاضي/يتشال لمحافظ زي `paypal`/`jawwal-manual`.

---

## رفع الإيصال

منطق الرفع نفسه موصوف بـ[`12-orders.md`](./12-orders.md) (`POST /orders/{id}/receipt`) — الفورم (`ReceiptUploadForm.tsx`) عنده وضعين حصرياً:

1. **رفع صورة**: ملف صورة واحد (`accept="image/*"`) — **مفيش تحقق حجم/نوع بالفرونت حالياً**، لازم يتحقق منه السيرفر (نوع MIME فعلي + حد أقصى للحجم، مثلاً 5MB) ويرفض غير المطابق بـ422.
2. **"واجهت مشكلة برفع الإيصال"**: ملاحظة نصية حرة بدل الصورة (اسم البنك/الحساب المحوّل منه) — يدوي للمطابقة من الموظف.

المبلغ (`amount`) وطريقة الدفع (`method`) بييجوا من سياق الطلب/طلب الشحن نفسه، مش من فورم الإيصال — الفورم بس يرفق الإثبات.

---

## ملاحظة: جوال باي "الآلي"

الفرونت فيه تدفق UI لـ"إرسال كود" و"إدخال كود" لطريقة `jawwal` (آلي) — بس ما تم التأكد من وجود تكامل فعلي مع Jawwal Pay API. **لو ما في اتفاقية فعلية مع جوال باي**، الأنسب تحويل `jawwal` (الآلي) لنفس نمط `jawwal-manual` (تحويل + رفع إيصال) لحد ما يصير تكامل حقيقي — بلغونا قبل ما نبني على افتراض API خارجي غير مؤكد.

---

## معايير قبول

- [ ] `GET /payment-accounts` يرجع الحسابات الحقيقية للمحل (مش بيانات تجريبية)
- [ ] رفع إيصال يتحقق من نوع/حجم الملف بالسيرفر
- [ ] توضيح إذا في تكامل حقيقي مع Jawwal Pay API أو لأ (يحدد شكل تدفق `jawwal`)
