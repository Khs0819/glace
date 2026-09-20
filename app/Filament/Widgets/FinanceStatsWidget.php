<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Wallet;
use App\Services\JawwalPay\JawwalPayClient;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The shop's money at a glance: what it owes its customers, what it took
 * today, and whether the payment gateway is answering.
 *
 * The first tile used to read "رصيد المحفظة" and show the merchant balance
 * pulled from Jawwal Pay — which read, on a dashboard, as the total of the
 * customers' wallets. They are different sums with different owners: one is
 * money the shop holds at the gateway, the other is money the shop owes its
 * customers. The wallets come first, because that is the figure the shop is
 * accountable for; the merchant balance is a detail of the gateway tile.
 */
class FinanceStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        return [
            $this->walletsStat(),
            $this->salesStat(),
            $this->unresolvedStat(),
            $this->gatewayStat(app(JawwalPayClient::class)),
        ];
    }

    /** What the shop owes its customers, across every wallet in the system. */
    private function walletsStat(): Stat
    {
        $total  = (float) Wallet::sum('balance');
        $loaded = Wallet::where('balance', '>', 0)->count();

        return Stat::make('مجموع أرصدة المحافظ', number_format($total, 2) . ' ₪')
            ->description($loaded === 0 ? 'لا توجد محافظ بها رصيد' : "{$loaded} محفظة بها رصيد")
            ->descriptionIcon('heroicon-m-wallet')
            ->color($total > 0 ? 'info' : 'gray');
    }

    private function salesStat(): Stat
    {
        // payment_status, not status: `status` carries the fulfilment stage
        // ("قيد المراجعة", "تم التسليم"), so matching it against "paid" never
        // matched anything and the tile read 0.00 ₪ on the busiest day.
        $paidToday = Order::where('payment_status', Order::STATUS_PAID)
            ->whereDate('paid_at', today());

        return Stat::make('مبيعات اليوم', number_format((float) $paidToday->sum('total'), 2) . ' ₪')
            ->description($paidToday->count() . ' طلب مدفوع')
            ->descriptionIcon('heroicon-m-receipt-percent')
            ->color('success');
    }

    private function unresolvedStat(): Stat
    {
        $unresolved = Payment::where('status', Payment::STATUS_UNRESOLVED)->count();

        return Stat::make('محاولات دفع غير مؤكدة', (string) $unresolved)
            ->description($unresolved > 0 ? 'تحتاج مراجعة يدوية' : 'لا شيء معلّق')
            ->descriptionIcon($unresolved > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
            ->color($unresolved > 0 ? 'danger' : 'gray');
    }

    /**
     * The gateway tile.
     *
     * Every failure is swallowed into a tile rather than thrown: the dashboard
     * has to load when Jawwal Pay is down, which is exactly when somebody will
     * be looking at it.
     */
    private function gatewayStat(JawwalPayClient $client): Stat
    {
        if (! config('services.jawwalpay.enabled')) {
            return Stat::make('بوابة جوال باي', 'مُطفأة')
                ->description('الدفع المباشر غير مفعّل')
                ->descriptionIcon('heroicon-m-pause-circle')
                ->color('gray');
        }

        if (! $client->configured()) {
            return Stat::make('بوابة جوال باي', 'غير مُعدّة')
                ->description('ناقص: ' . implode('، ', $client->missingConfig()))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger');
        }

        try {
            // Cached: this is a live call to the merchant account, and a page
            // load is not a reason to make one.
            $balance = Cache::remember('jawwalpay:dashboard-balance', now()->addMinutes(5), function () use ($client) {
                $info = $client->accountInfo();

                return collect($info['accounts'] ?? [])
                    ->firstWhere('accountType', 'WALLET')['balance'] ?? null;
            });
        } catch (Throwable) {
            return Stat::make('بوابة جوال باي', 'غير متاحة')
                ->description('تعذّر الاتصال')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning');
        }

        $merchant = $balance === null ? null : 'رصيد حساب التاجر: ' . number_format((float) $balance, 2) . ' ₪';

        /*
         * The only place a test environment is still named.
         *
         * Not a label on every tile — but a shop taking real orders against
         * the gateway's test host is charging nobody, and that has to be
         * visible rather than implied.
         */
        if ($client->sandbox()) {
            return Stat::make('بوابة جوال باي', 'متصلة بخادم التجربة')
                ->description('لا تُخصم مبالغ حقيقية — بدّل JAWWALPAY_BASE_URL إلى خادم الإنتاج')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning');
        }

        return Stat::make('بوابة جوال باي', 'متصلة')
            ->description($merchant ?? 'جاهزة لاستقبال الدفعات')
            ->descriptionIcon('heroicon-m-check-circle')
            ->color('success');
    }
}
