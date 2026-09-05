<?php

use App\Filament\Widgets\SmsStatusWidget;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * The tile that says whether customers can still log in.
 *
 * Login is passwordless, so an SMS account out of credit is not a degraded
 * feature — it is a locked door, and a silent one.
 */

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    // Each test decides what the provider says; none may reuse a prior answer.
    Illuminate\Support\Facades\Cache::forget('sms:dashboard-credits');
});

function useHotSms(): void
{
    config(['services.sms' => [
        'driver' => 'hotsms',
        'hotsms' => [
            'base_url' => 'https://hotsms.test',
            'username' => 'glace',
            'password' => 'secret',
            'sender'   => 'Glace',
        ],
    ]]);
}

it('keeps the dashboard up when the provider is unreachable', function () {
    useHotSms();
    Http::fake(fn () => throw new ConnectionException('down'));

    // The one day this matters is the day it is broken.
    Livewire::test(SmsStatusWidget::class)
        ->assertOk()
        ->assertSee('غير متاح');
});

it('warns when the credit is low enough to become an outage', function () {
    useHotSms();
    Http::fake(['*' => Http::response('60')]);

    Livewire::test(SmsStatusWidget::class)
        ->assertOk()
        ->assertSee('رصيد منخفض');
});

it('shows a healthy balance plainly', function () {
    useHotSms();
    Http::fake(['*' => Http::response('1000')]);

    Livewire::test(SmsStatusWidget::class)
        ->assertOk()
        ->assertSee('1,000')
        ->assertDontSee('رصيد منخفض');
});

it('flags a driver that cannot actually deliver a code', function () {
    config(['services.sms' => ['driver' => 'hotsms', 'hotsms' => ['base_url' => 'https://hotsms.test']]]);

    Livewire::test(SmsStatusWidget::class)
        ->assertOk()
        ->assertSee('ناقصة الإعداد');
});

it('does not call the provider at all when messages stay in the log', function () {
    config(['services.sms' => ['driver' => 'log']]);
    Http::fake();

    Livewire::test(SmsStatusWidget::class)
        ->assertOk()
        ->assertSee('معطّلة');

    Http::assertNothingSent();
});
