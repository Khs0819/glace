<?php

use App\Models\Customer;
use App\Models\OtpCode;
use App\Services\Auth\CustomerAuthService;
use App\Services\Auth\OtpService;
use Illuminate\Support\Facades\Hash;

/**
 * Passwordless sign-in (handoff 08) and the profile behind it (handoff 09).
 */

/** The code that was actually texted, which only the test can see. */
function issueCode(string $phone = '0599123456', string $code = '482913'): OtpCode
{
    return OtpCode::create([
        'phone'      => $phone,
        'purpose'    => OtpCode::PURPOSE_LOGIN,
        'code_hash'  => Hash::make($code),
        'expires_at' => now()->addMinutes(OtpService::TTL_MINUTES),
    ]);
}

function signedIn(?Customer $customer = null): array
{
    $customer ??= Customer::create(['name' => 'أحمد علي', 'phone' => '0599123456']);

    $token = app(CustomerAuthService::class)->issueToken($customer);

    return [$customer, ['Authorization' => $token]];
}

// ─── sending ────────────────────────────────────────────────────────────────

it('texts a code without revealing whether the number has an account', function () {
    $response = test()->postJson('/api/auth/otp/send', ['phone' => '0599123456']);

    $response->assertOk()->assertJson(['message' => 'تم إرسال رمز التحقق']);

    expect(OtpCode::where('phone', '0599123456')->count())->toBe(1);
});

it('stores the code hashed, never in the clear', function () {
    test()->postJson('/api/auth/otp/send', ['phone' => '0599123456'])->assertOk();

    $record = OtpCode::sole();

    // Six digits is a 10^6 space; a plaintext column would make a database
    // read enough to sign in as anybody.
    expect($record->code_hash)->not->toMatch('/^\d{6}$/')
        ->and($record->getAttributes())->not->toHaveKey('code');
});

it('normalises every form a customer might type into one account', function () {
    foreach (['0599123456', '+970599123456', '00970599123456', '970-599-123456'] as $typed) {
        OtpCode::query()->delete();

        test()->postJson('/api/auth/otp/send', ['phone' => $typed])->assertOk();

        expect(OtpCode::sole()->phone)->toBe('0599123456');
    }
});

it('rejects a number that is not a palestinian mobile', function () {
    test()->postJson('/api/auth/otp/send', ['phone' => '0412345678'])
        ->assertStatus(422)
        ->assertJsonPath('errors.phone.0', 'رقم الهاتف غير صحيح');
});

it('refuses a second code to the same number inside the cooldown', function () {
    test()->postJson('/api/auth/otp/send', ['phone' => '0599123456'])->assertOk();

    // 429, not 422: the storefront reads the status to keep its resend button
    // disabled rather than showing a field error.
    test()->postJson('/api/auth/otp/send', ['phone' => '0599123456'])->assertStatus(429);
});

it('lets the same number ask again once the cooldown has passed', function () {
    test()->postJson('/api/auth/otp/send', ['phone' => '0599123456'])->assertOk();

    $this->travel(OtpService::RESEND_COOLDOWN + 1)->seconds();

    test()->postJson('/api/auth/otp/send', ['phone' => '0599123456'])->assertOk();
});

it('caps how many codes one number can pull in an hour', function () {
    for ($i = 0; $i < OtpService::HOURLY_LIMIT; $i++) {
        test()->postJson('/api/auth/otp/send', ['phone' => '0599123456'])->assertOk();
        $this->travel(OtpService::RESEND_COOLDOWN + 1)->seconds();
    }

    // The cooldown is satisfied, so only the hourly ceiling can refuse this.
    test()->postJson('/api/auth/otp/send', ['phone' => '0599123456'])->assertStatus(429);
});

it('invalidates the previous code when a new one is sent', function () {
    $first = issueCode(code: '111111');

    $this->travel(OtpService::RESEND_COOLDOWN + 1)->seconds();
    test()->postJson('/api/auth/otp/send', ['phone' => '0599123456'])->assertOk();

    expect($first->fresh()->consumed_at)->not->toBeNull();

    test()->postJson('/api/auth/otp/verify', ['phone' => '0599123456', 'code' => '111111'])
        ->assertStatus(422);
});

// ─── verifying ──────────────────────────────────────────────────────────────

it('creates the account on a first sight of a number', function () {
    issueCode();

    test()->postJson('/api/auth/otp/verify', [
        'phone'    => '0599123456',
        'code'     => '482913',
        'fullName' => 'أحمد علي',
    ])
        ->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'phone']])
        ->assertJsonPath('user.name', 'أحمد علي')
        ->assertJsonPath('user.phone', '0599123456')
        // Never null: the storefront types it as `string`.
        ->assertJsonPath('user.email', '');

    expect(Customer::where('phone', '0599123456')->exists())->toBeTrue();
});

it('demands a name only when there is no account yet', function () {
    issueCode();

    test()->postJson('/api/auth/otp/verify', ['phone' => '0599123456', 'code' => '482913'])
        ->assertStatus(422)
        ->assertJsonPath('errors.fullName.0', 'الاسم مطلوب لإنشاء حساب جديد');
});

it('keeps the stored name when a returning customer sends a different one', function () {
    $customer = Customer::create(['name' => 'أحمد علي', 'phone' => '0599123456']);
    issueCode();

    // Otherwise anyone holding a code could rename the account they signed into.
    test()->postJson('/api/auth/otp/verify', [
        'phone'    => '0599123456',
        'code'     => '482913',
        'fullName' => 'اسم آخر',
    ])->assertOk()->assertJsonPath('user.name', 'أحمد علي');

    expect($customer->fresh()->name)->toBe('أحمد علي');
});

it('refuses a wrong code', function () {
    issueCode();

    test()->postJson('/api/auth/otp/verify', ['phone' => '0599123456', 'code' => '000000'])
        ->assertStatus(422)
        ->assertJsonPath('errors.code.0', 'رمز التحقق غير صحيح');
});

it('refuses an expired code', function () {
    issueCode();

    $this->travel(OtpService::TTL_MINUTES + 1)->minutes();

    test()->postJson('/api/auth/otp/verify', ['phone' => '0599123456', 'code' => '482913'])
        ->assertStatus(422);
});

it('burns a code after enough wrong guesses', function () {
    issueCode();

    for ($i = 0; $i < OtpCode::MAX_ATTEMPTS; $i++) {
        test()->postJson('/api/auth/otp/verify', ['phone' => '0599123456', 'code' => '000000'])
            ->assertStatus(422);
    }

    // The right code no longer works: brute force must not simply take longer,
    // it has to stop being possible.
    test()->postJson('/api/auth/otp/verify', ['phone' => '0599123456', 'code' => '482913'])
        ->assertStatus(422);
});

it('spends a code on first use so a replay cannot sign in again', function () {
    issueCode();

    test()->postJson('/api/auth/otp/verify', [
        'phone' => '0599123456', 'code' => '482913', 'fullName' => 'أحمد',
    ])->assertOk();

    test()->postJson('/api/auth/otp/verify', ['phone' => '0599123456', 'code' => '482913'])
        ->assertStatus(422);
});

it('answers a wrong code and an unrequested one identically', function () {
    // Otherwise the difference between the two tells an attacker which numbers
    // have a code in flight.
    $never = test()->postJson('/api/auth/otp/verify', ['phone' => '0599999999', 'code' => '482913']);

    issueCode();
    $wrong = test()->postJson('/api/auth/otp/verify', ['phone' => '0599123456', 'code' => '000000']);

    expect($never->json('errors.code.0'))->toBe($wrong->json('errors.code.0'));
});

// ─── tokens ─────────────────────────────────────────────────────────────────

it('stores only the hash of an issued token', function () {
    issueCode();

    $token = test()->postJson('/api/auth/otp/verify', [
        'phone' => '0599123456', 'code' => '482913', 'fullName' => 'أحمد',
    ])->json('token');

    $stored = Customer::sole()->tokens()->sole();

    expect($stored->token_hash)->toBe(hash('sha256', $token))
        ->and($stored->token_hash)->not->toBe($token);
});

it('accepts the bare authorization header the storefront sends', function () {
    [$customer, $headers] = signedIn();

    // No `Bearer` prefix — this is the whole reason the guard is hand-rolled.
    test()->getJson('/api/auth/me', $headers)
        ->assertOk()
        ->assertJsonPath('user.id', $customer->id);
});

it('accepts a bearer prefix too, so a helpful http client cannot log everyone out', function () {
    [$customer, $headers] = signedIn();

    test()->getJson('/api/auth/me', ['Authorization' => 'Bearer ' . $headers['Authorization']])
        ->assertOk()
        ->assertJsonPath('user.id', $customer->id);
});

it('rejects a missing or unknown token with the message the interceptor matches', function () {
    test()->getJson('/api/auth/me')->assertStatus(401)->assertJson(['message' => 'Unauthenticated']);

    test()->getJson('/api/auth/me', ['Authorization' => str_repeat('a', 64)])->assertStatus(401);
});

it('rejects an expired token', function () {
    [$customer, $headers] = signedIn();

    $this->travel(CustomerAuthService::TOKEN_TTL_DAYS + 1)->days();

    test()->getJson('/api/auth/me', $headers)->assertStatus(401);
});

it('refuses a blocked account', function () {
    [$customer, $headers] = signedIn();
    $customer->update(['blocked' => true]);

    test()->getJson('/api/auth/me', $headers)->assertStatus(401);
});

// ─── profile ────────────────────────────────────────────────────────────────

it('updates the profile without an email, which is the common case', function () {
    [$customer, $headers] = signedIn();

    test()->putJson('/api/auth/profile', ['name' => 'أحمد علي', 'phone' => '0599123456'], $headers)
        ->assertOk()
        ->assertJsonPath('user.name', 'أحمد علي')
        ->assertJsonPath('user.email', '');
});

it('accepts an email when one is given', function () {
    [$customer, $headers] = signedIn();

    test()->putJson('/api/auth/profile', [
        'name' => 'أحمد علي', 'email' => 'ahmed@example.com', 'phone' => '0599123456',
    ], $headers)
        ->assertOk()
        ->assertJsonPath('user.email', 'ahmed@example.com');
});

it('treats an empty email as clearing it, not as an error', function () {
    [$customer, $headers] = signedIn();
    $customer->update(['email' => 'old@example.com']);

    test()->putJson('/api/auth/profile', ['name' => 'أحمد', 'email' => ''], $headers)
        ->assertOk()
        ->assertJsonPath('user.email', '');

    expect($customer->fresh()->email)->toBeNull();
});

it('rejects an email that already belongs to someone else', function () {
    Customer::create(['name' => 'آخر', 'phone' => '0598000000', 'email' => 'taken@example.com']);
    [$customer, $headers] = signedIn();

    test()->putJson('/api/auth/profile', ['name' => 'أحمد', 'email' => 'taken@example.com'], $headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'البريد الإلكتروني مستخدم مسبقاً');
});

it('lets a customer keep their own email on save', function () {
    [$customer, $headers] = signedIn();
    $customer->update(['email' => 'mine@example.com']);

    test()->putJson('/api/auth/profile', ['name' => 'أحمد', 'email' => 'mine@example.com'], $headers)
        ->assertOk();
});

it('refuses a phone that is already another account', function () {
    Customer::create(['name' => 'آخر', 'phone' => '0598000000']);
    [$customer, $headers] = signedIn();

    test()->putJson('/api/auth/profile', ['name' => 'أحمد', 'phone' => '0598000000'], $headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.phone.0', 'رقم الهاتف مستخدم مسبقاً');
});

it('requires a token to read or write the profile', function () {
    test()->getJson('/api/auth/me')->assertStatus(401);
    test()->putJson('/api/auth/profile', ['name' => 'أحمد'])->assertStatus(401);
});

it('keeps storefront accounts out of the admin users table', function () {
    issueCode();

    test()->postJson('/api/auth/otp/verify', [
        'phone' => '0599123456', 'code' => '482913', 'fullName' => 'أحمد',
    ])->assertOk();

    // Filament's canAccessPanel() lets every `users` row in, so a customer
    // landing there would be a customer with a dashboard login.
    expect(App\Models\User::count())->toBe(0)
        ->and(Customer::count())->toBe(1);
});
