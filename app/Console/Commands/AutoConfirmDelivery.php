<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Console\Command;

/**
 * Auto-confirm delivery orders that the customer hasn't confirmed.
 *
 * Scheduled to run every 5 minutes. If a delivery order has been in
 * "تم التسليم" (delivered) for longer than the configured auto-confirm
 * window, it is moved to "تم الاستلام" (received).
 */
class AutoConfirmDelivery extends Command
{
    protected $signature   = 'orders:auto-confirm';
    protected $description = 'تأكيد استلام طلبات التوصيل تلقائياً بعد المدة المحددة';

    public function handle(): int
    {
        $minutes = StoreSetting::autoConfirmMinutes();
        $cutoff  = now()->subMinutes($minutes);

        $orders = Order::where('status', Order::FULFILMENT_DELIVERED)
            ->where('delivery_method', 'delivery')
            ->where('updated_at', '<=', $cutoff)
            ->get();

        if ($orders->isEmpty()) {
            $this->info('لا توجد طلبات تحتاج تأكيد تلقائي.');
            return 0;
        }

        foreach ($orders as $order) {
            $order->update(['status' => Order::FULFILMENT_RECEIVED]);
            $this->line("  ✅ {$order->reference} — تم تأكيد الاستلام تلقائياً");
        }

        $this->info("تم تأكيد {$orders->count()} طلب تلقائياً.");

        return 0;
    }
}
