# Unified Authentication API Reference

## Base URL
```
/api/auth
```

---

## 1. Send OTP

Sends a 6-digit verification code via SMS to the given phone number.

### Request
```
POST /api/auth/otp/send
Content-Type: application/json

{
  "phone": "05xxxxxxxx"
}
```

### Success Response (200)
```json
{
  "success": true,
  "message": "تم إرسال رمز التحقق",
  "data": {
    "userExists": true
  }
}
```

| Field | Type | Description |
|---|---|---|
| `data.userExists` | `boolean` | `true` if account exists (skip name input), `false` if new user (show name input) |

### Error Responses

| Status | Code | Message | When |
|---|---|---|---|
| 422 | `INVALID_PHONE` | رقم الهاتف غير صحيح | Phone is not a valid Palestinian mobile number |
| 429 | `TOO_MANY_REQUESTS` | الرجاء الانتظار قبل إعادة الإرسال | Resend cooldown (60s) or hourly limit (5) hit |
| 503 | `SMS_FAILURE` | تعذر إرسال الرسالة — حاول لاحقاً | SMS gateway refused delivery |

---

## 2. Verify OTP

Verifies the OTP code and returns a session token. Creates a new account if the phone number is new and `fullName` is provided.

### Request (Existing User)
```
POST /api/auth/otp/verify
Content-Type: application/json

{
  "phone": "05xxxxxxxx",
  "code": "123456"
}
```

### Request (New User)
```
POST /api/auth/otp/verify
Content-Type: application/json

{
  "phone": "05xxxxxxxx",
  "code": "123456",
  "fullName": "محمد علي"
}
```

### Success Response (200)
```json
{
  "success": true,
  "message": "تم تسجيل الدخول بنجاح",
  "data": {
    "token": "abc123...",
    "user": {
      "id": 1,
      "name": "محمد علي",
      "email": "",
      "phone": "0599123456"
    }
  }
}
```

### Error Responses

| Status | Code | Message | When |
|---|---|---|---|
| 422 | `INVALID_OTP_CODE` | رمز التحقق غير صحيح | Wrong, expired, or already-used code |
| 422 | `NAME_REQUIRED` | الاسم مطلوب لإنشاء حساب جديد | New user but `fullName` not provided |
| 403 | `ACCOUNT_BLOCKED` | هذا الحساب موقوف — يرجى التواصل معنا | Account is blocked |

---

## 3. Get Profile

Returns the authenticated user's profile.

### Request
```
GET /api/auth/me
Authorization: <token>
```

### Success Response (200)
```json
{
  "user": {
    "id": 1,
    "name": "محمد علي",
    "email": "",
    "phone": "0599123456"
  }
}
```

### Error Responses

| Status | Message | When |
|---|---|---|
| 401 | Unauthenticated | Missing, invalid, or expired token |

---

## 4. Update Profile

Updates the authenticated user's name, email, and/or phone.

### Request
```
PUT /api/auth/profile
Authorization: <token>
Content-Type: application/json

{
  "name": "محمد علي",
  "email": "ahmed@example.com",
  "phone": "0599123456"
}
```

### Notes
- `email` is optional. Omitting it leaves it unchanged. Sending `""` clears it.
- `phone` change is allowed only if not already taken by another account.

---

## Authentication

All authenticated endpoints accept the token as:
```
Authorization: <token>
```
or:
```
Authorization: Bearer <token>
```

Both formats are accepted. The token is a 64-character random string. Only its SHA-256 hash is stored server-side.

---

## OTP Security

| Property | Value |
|---|---|
| Code length | 6 digits |
| TTL | 5 minutes |
| Resend cooldown | 60 seconds (per phone) |
| Hourly limit | 5 codes per phone |
| Max wrong attempts | 5 (then code is burned) |
| Storage | SHA-256 hash only |
| Consumption | Single-use (consumed on verify) |

---

## Version
- **v1.0** (2026-09-13): Initial specification
