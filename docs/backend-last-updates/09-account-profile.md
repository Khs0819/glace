# 09 — الحساب: بروفايل المستخدم

**الأولوية:** عالية
**Endpoints:** `GET /auth/me` · `PUT /auth/profile` (لا يوجد `/auth/logout` — تسجيل الخروج client-side بالكامل، شوف تحت)
**الحالة الحيّة (2026-08-31):** الفرونت جاهز ومستنّي — `useMe.ts` / `useUpdateProfile.ts` مكتوبين على افتراض هاي الـendpoints موجودة، بس ما تم تأكيد إنهم فعلاً شغالين على السيرفر. `useLogout.ts` **ما بينادي أي API** — قرار 2026-08-31: تسجيل الخروج بيمسح `token`/`user` من الـstore المحلي وينظّف react-query cache فقط، بدون أي طلب شبكة.

`/my-account` صار عنده صفحات فرعية حقيقية (`/my-account/profile`, `/orders`, `/wallet`, `/addresses`, `/security`, `/help`, `/privacy`, `/terms`) بدل تابات بصفحة وحدة — الـendpoints المطلوبة هون بتخدم صفحة البروفايل تحديداً.

---

## `GET /auth/me`

```json
// 200 — Authorization: <token>
{ "user": { "id": 1, "name": "أحمد علي", "email": "ahmed@example.com", "phone": "0599123456" } }

// 401
{ "message": "Unauthenticated" }
```

## `PUT /auth/profile`

فورم البروفايل (`ProfilePanel.tsx`) عنده بالضبط 3 حقول. **قرار 2026-08-31: الإيميل اختياري بالكامل** (مش مجرد فارغ مقبول — الحقل نفسه ممكن ما يترسل/يترسل فاضي)، لأن المعرّف الأساسي هو رقم الهاتف (OTP)، مش الإيميل:

```ts
{
  name: string;    // required
  email?: string;  // optional — "" أو غير موجود لو المستخدم ما حط إيميل
  phone?: string;
}
```

```json
// Request (بإيميل)
{ "name": "أحمد علي", "email": "ahmed@example.com", "phone": "0599123456" }

// Request (بدون إيميل — حالة شائعة)
{ "name": "أحمد علي", "phone": "0599123456" }

// 200
{ "user": { "id": 1, "name": "أحمد علي", "email": "ahmed@example.com", "phone": "0599123456" } }

// 422 — فقط لو إيميل تم إرساله وكان مكرر/غير صالح، مش لأنه فاضي
{ "message": "البيانات غير صحيحة", "errors": { "email": ["البريد الإلكتروني مستخدم مسبقاً"] } }
```

مفيش حقل صورة بروفايل (avatar) ولا باسورد بهاد الفورم — الأفاتار بالواجهة حالياً حروف الاسم الأولى بس (initials)، مش صورة مرفوعة.

## تسجيل الخروج — لا يوجد endpoint

**قرار 2026-08-31:** ما فيش `/auth/logout` بهاد النظام. `useLogout.ts` بيمسح `token`/`user` من الـstore المحلي (zustand) وينظّف react-query cache، ثم يحوّل لـ`/auth/login` — كله client-side بدون أي طلب شبكة. لا داعي لتنفيذ endpoint خاص بالخروج.

---

## معايير قبول

- [ ] `GET /auth/me` يرجع بيانات المستخدم الحالي حسب التوكن
- [ ] `PUT /auth/profile` يعدّل `name`/`phone` ويقبل `email` اختياري (فاضي أو غير موجود)، ويرفض إيميل مكرر/غير صالح بـ422 فقط لو تم إرساله فعلياً
