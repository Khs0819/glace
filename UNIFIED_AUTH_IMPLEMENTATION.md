# Unified Authentication Flow - Frontend Implementation Summary

## ✅ Completed Changes

### 1. New Unified Auth Page
- **File**: `src/app/(main)/auth/page.tsx`
- Single entry point for both login and registration
- Redirects authenticated users to `/my-account`
- Supports redirect query parameter: `/auth?redirect=/checkout`

### 2. Unified Auth Form Component
- **File**: `src/components/Auth/UnifiedAuthForm.tsx`
- Wraps `PhoneOtpFlow` with authentication logic
- Handles redirect after successful login/registration

### 3. Refactored PhoneOtpFlow Component
- **File**: `src/components/Auth/PhoneOtpFlow.tsx`
- **Key Changes**:
  - Removed mode prop (no longer login/register specific)
  - Receives `userExists` flag from backend response
  - Conditionally shows name input only for new users
  - Shows error from OTP send endpoint
  - Dynamic button validation: disables submit if name is required but empty

### 4. Updated OTP Auth Hook
- **File**: `src/hooks/auth/useOtpAuth.ts`
- `useSendOtp()` now returns `SendOtpResponse` with `userExists` flag
- Error handling: extracts and throws backend error messages
- `useVerifyOtp()` unchanged (still handles login/registration)

### 5. Redirect Old Routes
- **`/auth/login`** → redirects to `/auth`
- **`/auth/register`** → redirects to `/auth`

### 6. Updated Navigation Links
- `src/hooks/auth/useLogout.ts`: `/auth/login` → `/auth`
- `src/components/Account/MyAccountClientPage.tsx`: `/auth/login` → `/auth`
- `src/components/Checkout/CheckoutClientPage.tsx`: `/auth/login` → `/auth`

---

## 📊 UI/UX Flow

### Step 1: Phone Input
```
User enters phone number
↓
Frontend sends to: POST /auth/otp/send
↓
Backend responds with:
{
  "success": true,
  "message": "تم إرسال رمز التحقق",
  "data": {
    "userExists": true/false
  }
}
```

### Step 2: OTP + Optional Name
```
If userExists = true (existing user):
  Show: Phone Edit Button + OTP Input + Resend Button
  
If userExists = false (new user):
  Show: Phone Edit Button + Name Input + OTP Input + Resend Button
  
User submits: POST /auth/otp/verify
{
  "phone": "05xxxxxxxx",
  "code": "123456",
  "fullName": "محمد علي"  // Only if new user
}
```

---

## 🔄 State Management

### Component State
```typescript
- step: "phone" | "otp"           // Current screen
- phone: string                    // User's phone number
- userExists: boolean              // From backend (send OTP response)
- code: string                     // 6-digit OTP
- fullName: string                 // Name (only for new users)
- secondsLeft: number              // Countdown timer
```

### Auth Store (Zustand)
- Stores JWT token and user data after verification
- Used to check if user is logged in
- Cleared on logout

---

## 🔗 API Integration Points

### 1. Send OTP
```
POST /auth/otp/send
Request: { phone: "05xxxxxxxx" }
Response: {
  success: true,
  message: string,
  data: {
    userExists: boolean
  }
}
```

### 2. Verify OTP
```
POST /auth/otp/verify
Request (Existing User): {
  phone: "05xxxxxxxx",
  code: "123456"
}
Request (New User): {
  phone: "05xxxxxxxx",
  code: "123456",
  fullName: "محمد علي"
}
Response: {
  success: true,
  message: string,
  data: {
    token: string,
    user: {
      id: number,
      name: string,
      email?: string,
      phone: string
    }
  }
}
```

---

## 🚀 Backend Requirements

### Required Changes in Laravel Backend

1. **Update `/auth/otp/send` endpoint**
   - Check if user exists in database
   - Return `userExists` flag in response
   - Keep existing OTP send logic
   - Handle error responses as documented

2. **Update `/auth/otp/verify` endpoint**
   - Handle optional `fullName` field
   - If user doesn't exist AND fullName provided:
     - Create new user account
     - Proceed to login
   - If user doesn't exist AND fullName NOT provided:
     - Return error: "الاسم مطلوب لإنشاء حساب جديد"
   - Keep existing token generation logic

3. **Error Response Format**
   - All errors must include `message` field in Arabic
   - Frontend displays this message directly to user
   - See `docs/UNIFIED_AUTH_API.md` for complete error codes

---

## 🧪 Testing Scenarios

### Scenario 1: New User Registration
```
1. User: 05xxxxxxxx (not in system)
2. Backend: userExists = false
3. UI: Shows name input
4. User: Enters OTP + Name
5. Backend: Creates user + Returns token
6. Result: ✅ User logged in
```

### Scenario 2: Existing User Login
```
1. User: 05xxxxxxxx (in system)
2. Backend: userExists = true
3. UI: Hides name input
4. User: Enters OTP only
5. Backend: Returns token
6. Result: ✅ User logged in
```

### Scenario 3: Wrong OTP
```
1. User: Enters wrong OTP
2. Backend: 401 INVALID_OTP_CODE
3. UI: Shows error message, clears OTP input
4. User: Can retry
```

### Scenario 4: New User Missing Name
```
1. User: 05xxxxxxxx (not in system)
2. Backend: userExists = false
3. UI: Shows name input
4. User: Tries to submit without name
5. UI: Button disabled (no submit possible)
6. User: Enters name, then OTP works
```

---

## 📝 Files Changed

### New Files
- `src/app/(main)/auth/page.tsx`
- `src/components/Auth/UnifiedAuthForm.tsx`
- `docs/UNIFIED_AUTH_API.md` (backend spec)
- `docs/UNIFIED_AUTH_IMPLEMENTATION.md` (this file)

### Modified Files
- `src/components/Auth/PhoneOtpFlow.tsx` (major refactor)
- `src/hooks/auth/useOtpAuth.ts` (added SendOtpResponse type)
- `src/hooks/auth/useLogout.ts`
- `src/components/Account/MyAccountClientPage.tsx`
- `src/components/Checkout/CheckoutClientPage.tsx`
- `src/app/(main)/auth/login/page.tsx` (now redirects)
- `src/app/(main)/auth/register/page.tsx` (now redirects)

### Unchanged
- `src/components/Auth/LoginForm.tsx` (no longer used)
- `src/components/Auth/RegisterForm.tsx` (no longer used)
- `src/store/authStore.ts`
- `src/components/Auth/OtpInput.tsx`
- `src/components/Auth/AuthLayout.tsx`

---

## 🔐 Security Notes

- Name field is optional for existing users (validation only on verify endpoint)
- OTP remains 6 digits (backend validates format)
- All error messages come from backend (no hardcoding possible)
- Phone validation regex matches Palestinian formats
- Token stored in auth store (persistent via Zustand persist)

---

## 🎯 Next Steps

1. **Backend**: Implement changes to both endpoints
2. **Testing**: Test all 4 scenarios above
3. **QA**: Verify redirect flows work correctly
4. **Deployment**: No database schema changes required

---

## 📞 Support Links

- API Documentation: `docs/UNIFIED_AUTH_API.md`
- Components: `src/components/Auth/`
- Hooks: `src/hooks/auth/`
- Routes: `src/app/(main)/auth/`

---

## Version
- **v1.0** (2026-09-13): Initial implementation complete
- **Status**: ✅ Ready for backend integration
