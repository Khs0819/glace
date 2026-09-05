<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cancel')
                ->label('إلغاء الطلب')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (Order $record) => ! $record->isPaid() && $record->status !== Order::STATUS_CANCELLED)
                ->requiresConfirmation()
                ->modalDescription('لن يعود بالإمكان دفع هذا الطلب بعد الإلغاء.')
                ->action(fn (Order $record) => $record->update(['status' => Order::STATUS_CANCELLED])),
        ];
    }
}
