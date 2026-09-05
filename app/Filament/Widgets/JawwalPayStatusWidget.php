<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Payment;
use App\Services\JawwalPay\JawwalPayClient;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Whether the payment gateway is reachable, and what it has taken today.
 *
 * The balance call is cached and every failure is swallowed into a "not
 * available" tile on purpose: the dashboard must still load when Jawwal Pay is
 * down, which is exactly when someone will be looking at it.
 */
class JawwalPayStatusWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $client = app(JawwalPayClient::class);

        $paidToday = Order::where('status', Order::STATUS_PAID)
            ->whereDate('paid_at', today());

        $unresolved = Payment::where('status', Payment::STATUS_UNRESOLVED)->count();

        return [
            $this->connectionStat($client),

            Stat::make('مبيعات اليوم', number_format((float) $paidToday->sum('total'), 2) . ' ₪')
                ->description($paidToday->count() . ' طلب مدفوع')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('success'),

            Stat::make('محاولات غير مؤكدة', (string) $unresolved)
                ->description($unresolved > 0 ? 'تحتاج مراجعة يدوية' : 'لا شيء معلّق')
                ->descriptionIcon($unresolved > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($unresolved > 0 ? 'danger' : 'gray'),
        ];
    }

    private function connectionStat(JawwalPayClient $client): Stat
    {
        if (! $client->configured()) {
            return Stat::make('بوابة الدفع', 'غير مُعدّة')
                ->description('ناقص: ' . implode('، ', $client->missingConfig()))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger');
        }

        $environment = $client->sandbox() ? 'بيئة اختبار' : 'بيئة إنتاج';

        try {
            // Cached: this is a live call to the merchant account, and the
            // dashboard is not a reason to make one on every page load.
            $balance = Cache::remember('jawwalpay:dashboard-balance', now()->addMinutes(5), function () use ($client) {
                $info = $client->accountInfo();

                return collect($info['accounts'] ?? [])
                    ->firstWhere('accountType', 'WALLET')['balance'] ?? null;
            });
        } catch (Throwable $e) {
            return Stat::make('بوابة الدفع', 'غير متاحة')
                ->description($environment . ' — تعذّر الاتصال')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning');
        }

        return Stat::make(
            'رصيد المحفظة',
            $balance === null ? '—' : number_format((float) $balance, 2) . ' ₪',
        )
            ->description($environment . ' — متصلة')
            ->descriptionIcon('heroicon-m-check-circle')
            ->color($client->sandbox() ? 'warning' : 'success');
    }
}
