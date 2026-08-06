<?php

namespace App\Filament\Resources\ContactResource\Pages;

use App\Filament\Resources\ContactResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewContact extends ViewRecord
{
    protected static string $resource = ContactResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Auto-mark as read when opened
        if (isset($data['id'])) {
            $this->record->update(['is_read' => true]);
        }
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mark_unread')
                ->label('تحديد كغير مقروء')
                ->icon('heroicon-o-envelope')
                ->color('warning')
                ->visible(fn () => (bool) $this->record->is_read)
                ->action(fn () => $this->record->update(['is_read' => false])),
        ];
    }
}
