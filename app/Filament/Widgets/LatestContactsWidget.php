<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ContactResource;
use App\Models\Contact;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestContactsWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';
    protected static ?string $heading = 'آخر رسائل التواصل';

    public function table(Table $table): Table
    {
        return $table
            ->query(Contact::latest()->limit(6))
            ->columns([
                Tables\Columns\IconColumn::make('is_read')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-o-envelope-open')
                    ->falseIcon('heroicon-o-envelope')
                    ->trueColor('gray')
                    ->falseColor('danger')
                    ->width(40),
                Tables\Columns\TextColumn::make('name')
                    ->label('المُرسِل')
                    ->weight(fn ($record) => $record->is_read
                        ? \Filament\Support\Enums\FontWeight::Normal
                        : \Filament\Support\Enums\FontWeight::Bold)
                    ->description(fn ($record) => $record->email),
                Tables\Columns\TextColumn::make('message')
                    ->label('الرسالة')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->message),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('الوقت')
                    ->dateTime('d/m/Y H:i')
                    ->badge()
                    ->color(fn ($record) => $record->created_at->isToday() ? 'success' : 'gray'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('عرض')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => ContactResource::getUrl('view', ['record' => $record])),
            ])
            ->paginated(false);
    }
}
