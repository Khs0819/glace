# 14 — المحفظة: رصيد + شحن + مراجعة طلبات الشحن

**الأولوية:** متوسطة
**Endpoints:** `GET /wallet` · `POST /wallet/deduct` · `POST /wallet/topup-requests` · `GET /wallet/topup-requests` · (إداري) موافقة على طلب شحن
**الحالة الحيّة (2026-08-31):** كل شي بـ`localStorage` (`walletStore.ts`) — الرصيد وهمي بالكامل، وفيه دالة `approveTopUpRequest` بالفرونت نفسه شغالة **كبديل مؤقت للوحة تحكم إدارية غير موجودة** (أي حد يقدر تقنياً "يوافق" على شحن نفسه من كونسول المتصفح — لازم ينتقل هاد القرار للسيرفر فقط).

---

## شكل البيانات

```ts
type TopUpMethod = "bop" | "paypal" | "jawwal" | "jawwal-manual";

interface WalletTransaction {
  id: string;
  date: string;                 // ISO
  amount: number;
  type: "credit" | "debit";
  label: string;
  method?: TopUpMethod | "cash" | "wallet";
  receiptImage?: string;
}

type TopUpRequestStatus = "قيد المراجعة" | "مكتمل";

interface TopUpRequest {
  id: string;
  amount: number;
  method: TopUpMethod;
  status: TopUpRequestStatus;
  createdAt: string;
  receiptImage?: string;
  receiptNote?: string;
  phone?: string;                // فقط لطريقة jawwal الآلية
}
```

---

## `GET /wallet`

```json
{
  "balance": 105.25,
  "transactions": [
    { "id": "t1", "date": "2026-08-30T10:00:00Z", "amount": 50, "type": "credit", "label": "شحن رصيد", "method": "bop", "receiptImage": "https://.../receipts/r1.png" }
  ]
}
```

## `POST /wallet/topup-requests` — تقديم طلب شحن

```json
// Request
{ "amount": 50, "method": "bop", "receiptImage": "<file>", "receiptNote": null, "phone": null }

// 201
{ "request": { "id": "tr1", "amount": 50, "method": "bop", "status": "قيد المراجعة", "createdAt": "2026-08-31T10:00:00Z" } }
```

**لا يضيف الرصيد فوراً** — يضل `"قيد المراجعة"` لحد ما موظف يوافق عليه من لوحة التحكم (Filament)، بنفس منطق `RECEIPT_METHODS` بالطلبات ([`12-orders.md`](./12-orders.md)). زي رفع الإيصال بالطلبات، `receiptImage` لازم يتحول لرفع ملف حقيقي (multipart) بدل base64.

## `GET /wallet/topup-requests`
سجل طلبات الشحن الخاصة بالمستخدم (تُعرض بصفحة "طلبات الشحن" بالمحفظة).

## موافقة على طلب شحن (إداري فقط، من Filament)

**لازم تصير من لوحة التحكم حصراً، مش من أي endpoint يقدر المستخدم يوصله.** عند الموافقة:
1. `status` يتحول لـ`"مكتمل"`.
2. `balance` يزيد بقيمة `amount`.
3. تنضاف حركة `WalletTransaction` جديدة (`type: "credit"`) بنفس `method`/`receiptImage` تبع طلب الشحن، عشان "سجل المعاملات" يعرض نفس إثبات التحويل اللي انراجع.

## `POST /wallet/deduct`
خصم من الرصيد وقت الدفع بطريقة `wallet` على طلب — يرجع خطأ واضح لو الرصيد غير كافي (409 مثلاً)، السيرفر هو الفيصل، مش تحقق بالفرونت بس.

```json
// Request
{ "amount": 22.5, "label": "دفع طلب #ORD-M3K2" }
// 200
{ "balance": 82.75 }
// 409 — رصيد غير كافي
{ "message": "الرصيد غير كافٍ" }
```

---

## معايير قبول

- [ ] الرصيد وسجل المعاملات مرتبطين بحساب المستخدم على السيرفر، مش المتصفح
- [ ] طلب شحن جديد يضل "قيد المراجعة" ولا يضيف رصيد إلا بموافقة إدارية فعلية من Filament
- [ ] لا يوجد أي endpoint يسمح للمستخدم نفسه بالموافقة على طلب شحنه
- [ ] خصم من المحفظة يترفض لو الرصيد غير كافٍ (يتحقق منه السيرفر لا الفرونت)
