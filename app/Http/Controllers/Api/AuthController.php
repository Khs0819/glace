<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\OtpCode;
use App\Services\Auth\CustomerAuthService;
use App\Services\Auth\OtpService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Passwordless sign-in and the profile behind it (handoff 08 · 09).
 *
 * There is no password anywhere in this system: no login-with-password, no
 * reset, no change. A phone number plus a code that was texted to it is the
 * whole of the credential story.
 *
 * There is also no logout endpoint, by decision (handoff 09) — the storefront
 * drops its stored token and clears its cache client-side.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly CustomerAuthService $auth,
    ) {}

    /** Text a code to the number. Says nothing about whether an account exists. */
    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $this->otp->send($request->validated('phone'));

        return response()->json(['message' => 'تم إرسال رمز التحقق']);
    }

    /**
     * Check the code, then hand back a token and the account.
     *
     * The same call signs in and registers: a number nobody has used before
     * becomes an account here, using the `fullName` sent alongside.
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->otp->verify($data['phone'], $data['code'], OtpCode::PURPOSE_LOGIN);

        $customer = $this->auth->resolveCustomer($data['phone'], $data['fullName'] ?? null);

        return response()->json([
            'token' => $this->auth->issueToken($customer, $request),
            'user'  => new CustomerResource($customer),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => new CustomerResource($request->user())]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();
        $data     = $request->validated();

        $attributes = ['name' => $data['name']];

        // handoff 09: e-mail is optional for the life of the account. An absent
        // key leaves it alone; an empty string clears it. Both are normal, and
        // neither is a validation failure.
        if ($request->has('email')) {
            $attributes['email'] = trim((string) ($data['email'] ?? '')) ?: null;
        }

        // Changing the phone changes the login identity, so it is only allowed
        // to a number that is not already somebody else's account.
        if (filled($data['phone'] ?? null)) {
            $phone = PhoneNumber::normalize($data['phone']);

            if ($phone === null) {
                throw ValidationException::withMessages(['phone' => 'رقم الهاتف غير صحيح']);
            }

            if (Customer::where('phone', $phone)->whereKeyNot($customer->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'phone' => 'رقم الهاتف مستخدم مسبقاً',
                ]);
            }

            $attributes['phone'] = $phone;
        }

        $customer->update($attributes);

        return response()->json(['user' => new CustomerResource($customer->fresh())]);
    }
}
