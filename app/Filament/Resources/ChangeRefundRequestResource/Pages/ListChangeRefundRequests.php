<?php

namespace App\Filament\Resources\ChangeRefundRequestResource\Pages;

use App\Filament\Resources\ChangeRefundRequestResource;
use App\Models\ChangeRefundRequest;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

/**
 * The refunds list, in the two halves it is actually used as.
 *
 * **بانتظار التحويل** is the work: what the accountant owes somebody today,
 * with the total beside it so the sum can be seen before the day is closed.
 *
 * The rest is the archive. A refund does not stop mattering once the transfer
 * is sent — it is what the shop reads when a customer rings back a week later
 * asking where their money went, and what the month is reconciled against. So
 * nothing is hidden or deleted; it moves one tab across.
 */
class ListChangeRefundRequests extends ListRecords
{
    protected static string $resource = ChangeRefundRequestResource::class;

    public function getTabs(): array
    {
        $count = fn (?string $status) => ChangeRefundRequest::query()
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->count();

        return [
            'pending' => Tab::make('بانتظار التحويل')
                ->icon('heroicon-o-clock')
                ->badge($count(ChangeRefundRequest::STATUS_PENDING) ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ChangeRefundRequest::STATUS_PENDING)),

            'completed' => Tab::make('تم التحويل')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('status', ChangeRefundRequest::STATUS_COMPLETED)
                    // Newest transfer first: the archive is read backwards from
                    // today, not forwards from when the request was raised.
                    ->reorder('reviewed_at', 'desc')),

            'rejected' => Tab::make('مرفوض')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ChangeRefundRequest::STATUS_REJECTED)),

            'orders' => Tab::make('استرداد طلبات')
                ->icon('heroicon-o-receipt-refund')
                ->badgeColor('danger')
                // A whole order given back is a different kind of event from
                // the change out of a cash sale, and is looked for on its own.
                ->modifyQueryUsing(fn (Builder $query) => $query->where('kind', ChangeRefundRequest::KIND_ORDER)),

            'all' => Tab::make('الكل')
                ->badge($count(null) ?: null)
                ->badgeColor('gray'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'pending';
    }
}
