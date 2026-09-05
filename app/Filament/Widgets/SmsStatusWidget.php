<?php

namespace App\Filament\Widgets;

use App\Models\OtpCode;
use App\Services\Sms\SmsSender;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Whether customers can still log in.
 *
 * This is not a courtesy tile. The storefront login is passwordless, so an SMS
 * account that has run out of credit does not degrade anything — it locks every
 * customer out at once, and it does so silently: the gateway accepts nothing,
 * the shop sees no error, and the only symptom is that people stop signing in.
 * Sixty remaining messages is a Thursday afternoon, not a warning.
 *
 * Failures collapse into a neutral tile rather than an exception, for the same
 * reason as the payment widget: the dashboard has to load on exactly the day
 * the provider is down.
 */
class SmsStatusWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    /** Below this, someone has to top up before it becomes an outage. */
    private const LOW_CREDIT = 100;

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $sender = app(SmsSender::class);

        $sentToday = OtpCode::whereDate('created_at', today())->count();

        return [
            $this->channelStat($sender),
            $this->creditStat($sender),

            Stat::make('رموز أُرسلت اليوم', (string) $sentToday)
                ->description('طلبات تسجيل دخول')
                ->descriptionIcon('heroicon-m-device-phone-mobile')
                ->color('gray'),
        ];
    }

    private function channelStat(SmsSender $sender): Stat
    {
        if (! $sender->live()) {
            // In production this is not a configuration choice, it is an
            // outage waiting to be discovered by a customer.
            return Stat::make('قناة الرسائل', 'معطّلة')
                ->description(app()->environment('production')
                    ? 'الرموز لا تصل لأحد — اضبط SMS_DRIVER'
                    : 'وضع التطوير — الرموز تُكتب في السجل')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color(app()->environment('production') ? 'danger' : 'warning');
        }

        if (! $sender->ready()) {
            return Stat::make('قناة الرسائل', 'ناقصة الإعداد')
                ->description($sender->driver() . ' — راجع بيانات الاتصال')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger');
        }

        return Stat::make('قناة الرسائل', $sender->driver())
            ->description('مهيّأة للإرسال')
            ->descriptionIcon('heroicon-m-check-circle')
            ->color('success');
    }

    private function creditStat(SmsSender $sender): Stat
    {
        $credits = $this->credits($sender);

        if ($credits === null) {
            return Stat::make('رصيد الرسائل', '—')
                ->description('غير متاح من المزوّد')
                ->descriptionIcon('heroicon-m-question-mark-circle')
                ->color('gray');
        }

        $low = $credits < self::LOW_CREDIT;

        return Stat::make('رصيد الرسائل', number_format($credits))
            ->description($low ? 'رصيد منخفض — اشحن قبل أن يتوقف الدخول' : 'رسالة متبقية')
            ->descriptionIcon($low ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
            ->color($low ? 'danger' : 'success');
    }

    private function credits(SmsSender $sender): ?float
    {
        try {
            // A live call to the provider; the dashboard is not a reason to
            // make one on every page load.
            return Cache::remember('sms:dashboard-credits', now()->addMinutes(10), fn () => match ($sender->driver()) {
                'hotsms' => $sender->hotsms()->credits(),
                'rest'   => $sender->rest()->credits(),
                default  => null,
            });
        } catch (Throwable) {
            return null;
        }
    }
}
