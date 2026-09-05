<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\Payment;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tabs follow the kitchen's day, not the ledger's.
 *
 * The first four are the fulfilment queue — what someone has to physically do
 * next. Payment state gets one tab of its own, because an unpaid order still
 * has to be made and a paid one still has to go out.
 */
class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل'),

            // A charge we never got an answer for outranks everything: money may
            // have moved and only a human can find out.
            'needs_review' => Tab::make('تحتاج مراجعة')
                ->icon('heroicon-o-exclamation-triangle')
                ->badgeColor('danger')
                ->badge(fn () => Payment::where('status', Payment::STATUS_UNRESOLVED)->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'payments',
                    fn (Builder $q) => $q->where('status', Payment::STATUS_UNRESOLVED),
                )),

            'new' => Tab::make('جديدة')
                ->badgeColor('warning')
                ->badge(fn () => Order::where('status', Order::FULFILMENT_REVIEW)->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Order::FULFILMENT_REVIEW)),

            'preparing' => Tab::make('قيد التحضير')
                ->badge(fn () => Order::whereIn('status', [
                    Order::FULFILMENT_PREPARING,
                    Order::FULFILMENT_READY,
                ])->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    Order::FULFILMENT_PREPARING,
                    Order::FULFILMENT_READY,
                ])),

            'on_way' => Tab::make('في الطريق')
                ->badgeColor('info')
                ->badge(fn () => Order::where('status', Order::FULFILMENT_ON_WAY)->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Order::FULFILMENT_ON_WAY)),

            'unpaid' => Tab::make('بانتظار الدفع')
                ->badge(fn () => Order::whereIn('payment_status', [
                    Order::STATUS_PENDING,
                    Order::STATUS_AWAITING_PAYMENT,
                ])->whereNotIn('status', Order::FINAL_STATUSES)->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereIn('payment_status', [Order::STATUS_PENDING, Order::STATUS_AWAITING_PAYMENT])
                    ->whereNotIn('status', Order::FINAL_STATUSES)),

            // The three channels, because they are three different jobs: a table
            // to carry to, a bag to hand over, a driver to dispatch.
            'dine_in' => Tab::make('داخل المحل')
                ->icon('heroicon-o-building-storefront')
                ->badge(fn () => Order::where('delivery_method', 'dine-in')
                    ->whereNotIn('status', Order::FINAL_STATUSES)->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('delivery_method', 'dine-in')),

            'pickup' => Tab::make('استلام')
                ->icon('heroicon-o-shopping-bag')
                ->badge(fn () => Order::where('delivery_method', 'pickup')
                    ->whereNotIn('status', Order::FINAL_STATUSES)->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('delivery_method', 'pickup')),

            'delivery' => Tab::make('توصيل')
                ->icon('heroicon-o-truck')
                ->badge(fn () => Order::where('delivery_method', 'delivery')
                    ->whereNotIn('status', Order::FINAL_STATUSES)->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('delivery_method', 'delivery')),

            'done' => Tab::make('منتهية')
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', Order::FINAL_STATUSES)),
        ];
    }
}
