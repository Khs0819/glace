<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * The two accounts the shop signs in with, and the one it did not know it had.
 */

it('creates a manager and a counter account, and prints each password once', function () {
    $this->artisan('staff:setup')
        ->expectsOutputToContain('admin@glaceelameer.com')
        ->expectsOutputToContain('cashier@glaceelameer.com')
        ->assertSuccessful();

    $manager = User::where('email', 'admin@glaceelameer.com')->sole();
    $counter = User::where('email', 'cashier@glaceelameer.com')->sole();

    expect($manager->isManager())->toBeTrue()
        ->and($counter->isAccountant())->toBeTrue();
});

it('leaves an existing password alone unless asked to rotate it', function () {
    $this->artisan('staff:setup', ['--manager-password' => 'first-password-here'])->assertSuccessful();

    // Running it again must not sign the manager out mid-shift.
    $this->artisan('staff:setup')->assertSuccessful();

    expect(Hash::check('first-password-here', User::where('email', 'admin@glaceelameer.com')->sole()->password))
        ->toBeTrue();

    $this->artisan('staff:setup', ['--rotate' => true])->assertSuccessful();

    expect(Hash::check('first-password-here', User::where('email', 'admin@glaceelameer.com')->sole()->password))
        ->toBeFalse();
});

it('puts a drifted role right without touching the password', function () {
    $user = User::factory()->accountant()->create([
        'email'    => 'admin@glaceelameer.com',
        'password' => 'kept-as-it-was',
    ]);

    $this->artisan('staff:setup')->assertSuccessful();

    expect($user->fresh()->isManager())->toBeTrue()
        ->and(Hash::check('kept-as-it-was', $user->fresh()->password))->toBeTrue();
});

it('names any account still on the seeder password', function () {
    // The old DatabaseSeeder created this on every install that ran db:seed to
    // get its menu — a full manager, with a password anybody could guess.
    User::factory()->create(['email' => 'admin@glace.com', 'password' => 'admin123456']);

    $this->artisan('staff:setup')
        ->expectsOutputToContain('كلمة السر الافتراضية القديمة')
        ->expectsOutputToContain('admin@glace.com')
        ->assertSuccessful();
});

it('says nothing about default passwords when there are none', function () {
    $this->artisan('staff:setup')
        ->doesntExpectOutputToContain('كلمة السر الافتراضية القديمة')
        ->assertSuccessful();
});

it('no longer seeds a login of its own', function () {
    $this->artisan('db:seed', ['--class' => 'Database\Seeders\DatabaseSeeder'])->assertSuccessful();

    expect(User::where('email', 'admin@glace.com')->exists())->toBeFalse();
});
