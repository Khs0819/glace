# 08 — تسجيل الدخول: OTP بدل الباسورد

**الأولوية:** عالية جداً (يمنع أي مستخدم من تسجيل دخول حقيقي)
**Endpoints:** `POST /auth/otp/send` · `POST /auth/otp/verify`
**الحالة الحيّة (2026-08-31):** الفرونت بالكامل انبنى على OTP، **مفيش أي endpoint حقيقي** — `useOtpAuth.ts` حالياً mock 100% (كود ثابت `123456`، توكن وهمي `fake-token-...`). الـswagger لسا فيه سكيمات باسورد قديمة (`LoginRequest`, `RegisterRequest`, `RestorePasswordRequest`, `NewPasswordRequest`) **لازم تتشال أو تتعتبر ملغاة** — صفحات `/auth/restore-password` و`/auth/new-password` والباسورد بالكامل انحذفوا من الفرونت.

---

## تدفق تسجيل الدخول/التسجيل (نفس الشاشة لكليهما)

1. المستخدم يدخل رقم الهاتف فقط (`PhoneFormValues { phone }` أو مع `fullName` بالتسجيل).
2. `POST /auth/otp/send` يرسل كود عبر SMS لهاد الرقم.
3. المستخدم يدخل الكود (6 أرقام) عبر `OtpFormValues { code }`.
4. `POST /auth/otp/verify` يتحقق من الكود، ويرجع `token` + `user` — وإذا الرقم جديد (أول مرة) بينشئ حساب باستخدام `fullName` المرسل مع الطلب.

**مفيش باسورد نهائياً** — لا تسجيل دخول، لا "نسيت كلمة المرور"، لا تغيير كلمة مرور. `SecurityPanel` بالحساب صار فيه فقط زر تسجيل خروج.

---

## `POST /auth/otp/send`

```json
// Request
{ "phone": "0599123456" }

// 200
{ "message": "تم إرسال رمز التحقق" }

// 422 — رقم غير صالح
{ "message": "رقم الهاتف غير صحيح", "errors": { "phone": ["..."] } }

// 429 — طلب متكرر بسرعة (throttle)
{ "message": "الرجاء الانتظار قبل إعادة الإرسال" }
```

- لازم rate-limit فعلي على السيرفر (مو مجرد تعطيل الزر بالفرونت) — نفس الرقم ما ينرسله كود جديد قبل ~60 ثانية.
- الكود لازم يكون قصير الصلاحية (مثلاً 5 دقائق) ويُخزّن مشفّر/hashed، مش نص صريح.

## `POST /auth/otp/verify`

```json
// Request (تسجيل دخول لرقم موجود)
{ "phone": "0599123456", "code": "482913" }

// Request (أول مرة / تسجيل حساب جديد)
{ "phone": "0599123456", "code": "482913", "fullName": "أحمد علي" }

// 200
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user": { "id": 1, "name": "أحمد علي", "email": "", "phone": "0599123456" }
}

// 422 — كود غلط أو منتهي
{ "message": "رمز التحقق غير صحيح" }
```

- `fullName` اختياري — بس مطلوب فعلياً أول مرة (رقم جديد بدون حساب). إذا الرقم موجود مسبقاً، تجاهل `fullName` المرسل واستخدم الاسم المخزّن.
- التوكن يُستخدم بعدين كـ `Authorization: <token>` (بدون `Bearer` prefix — هيك الـaxios interceptor الحالي مبني، شوف `bearerAuth` بالـswagger).
- بعد النجاح الفرونت يحفظ `token`+`user` ويحوّل لـ`/my-account` أو لصفحة `redirect` query param.

---

## `User` / `AuthUser` — الشكل المتوقع من الفرونت

```ts
interface AuthUser {
  id: number;
  name: string;
  email: string;   // ممكن يكون "" لو ما انسجل إيميل أبداً (اختياري فعلياً)
  phone?: string;
}
```

هاد الشكل مستخدم أيضاً بـ `GET /auth/me` و`PUT /auth/profile` (شوف [`09-account-profile.md`](./09-account-profile.md)) — خليه نفس الشكل بكل مكان.

---

## معايير قبول

- [ ] `POST /auth/otp/send` يرسل SMS فعلي، مع throttle على نفس الرقم
- [ ] `POST /auth/otp/verify` يرجع `token`+`user` صحيحين، وينشئ حساب جديد أول مرة باستخدام `fullName`
- [ ] كود منتهي/غلط يرجع 422 برسالة واضحة
- [ ] `LoginRequest`/`RegisterRequest`/`RestorePasswordRequest`/`NewPasswordRequest`/`ChangePasswordRequest` تتشال من `swagger.yaml` (ملغاة بالكامل، مفيش باسورد بالنظام)
