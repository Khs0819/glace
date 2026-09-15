<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Console\Command;

/**
 * Auto-confirm delivery orders that the customer hasn't confirmed.
 *
 * Scheduled to run every 5 minutes. If a delivery order has been
 * "في الطريق" (on the way) for longer than the configured window,
 * it is moved to "تم الاستلام" (received).
 */
class AutoConfirmDelivery extends Command
{
    protected $signature   = 'orders:auto-confirm';
    protected $description = 'تأكيد استلام طلبات التوصيل تلقائياً بعد المدة المحددة';

    public function handle(): int
    {
        $minutes = StoreSetting::autoConfirmMinutes();
        $cutoff  = now()->subMinutes($minutes);

        // Timed from when the driver took it, not from `updated_at`: any edit
        // to the order — even marking its receipt printed — moves `updated_at`
        // and would restart the clock indefinitely.
        $orders = Order::where('status', Order::FULFILMENT_ON_WAY)
            ->where('delivery_method', 'delivery')
            ->where(fn ($query) => $query
                ->where('driver_assigned_at', '<=', $cutoff)
                ->orWhere(fn ($q) => $q->whereNull('driver_assigned_at')->where('updated_at', '<=', $cutoff)))
            ->get();

        if ($orders->isEmpty()) {
            $this->info('لا توجد طلبات تحتاج تأكيد تلقائي.');
            return 0;
        }

        foreach ($orders as $order) {
            $order->update([
                'status'      => Order::FULFILMENT_RECEIVED,
                'received_at' => now(),
            ]);

            // The fee was booked to the driver when they were chosen; this only
            // records when the order actually arrived.
            \App\Models\DriverSettlement::where('order_id', $order->getKey())
                ->whereNull('delivered_at')
                ->update(['delivered_at' => now()]);
            $this->line("  ✅ {$order->reference} — تم تأكيد الاستلام تلقائياً");
        }

        $this->info("تم تأكيد {$orders->count()} طلب تلقائياً.");

        return 0;
    }
}
