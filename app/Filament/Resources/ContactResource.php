<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages;
use App\Models\Contact;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;
    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationGroup = 'الرسائل';
    protected static ?string $navigationLabel = 'صندوق الرسائل';
    protected static ?string $modelLabel = 'رسالة';
    protected static ?string $pluralModelLabel = 'الرسائل';
    protected static ?int $navigationSort = 30;

    public static function getNavigationBadge(): ?string
    {
        $unread = static::getModel()::where('is_read', false)->count();
        return $unread > 0 ? (string) $unread : null;
    }

    public static function getNavigationBadgeColor(): ?string { return 'danger'; }

    public static function form(Form $form): Form
    {
        return $form->schema([/* read-only — use infolist instead */]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('معلومات المُرسِل')
                ->icon('heroicon-o-user')
                ->schema([
                    Infolists\Components\TextEntry::make('name')
                        ->label('الاسم')
                        ->weight(\Filament\Support\Enums\FontWeight::Bold),
                    Infolists\Components\TextEntry::make('email')
                        ->label('البريد الإلكتروني')
                        ->copyable()
                        ->icon('heroicon-m-envelope'),
                    Infolists\Components\TextEntry::make('phone')
                        ->label('رقم الهاتف')
                        ->copyable()
                        ->icon('heroicon-m-phone'),
                    Infolists\Components\TextEntry::make('subject')
                        ->label('الموضوع')
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label('تاريخ الإرسال')
                        ->dateTime('d/m/Y — H:i')
                        ->icon('heroicon-m-clock')
                        ->badge()
                        ->color('gray'),
                    Infolists\Components\IconEntry::make('is_read')
                        ->label('الحالة')
                        ->boolean()
                        ->trueIcon('heroicon-o-envelope-open')
                        ->falseIcon('heroicon-o-envelope')
                        ->trueColor('success')
                        ->falseColor('warning'),
                ])->columns(2),

            Infolists\Components\Section::make('نص الرسالة')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->schema([
                    Infolists\Components\TextEntry::make('message')
                        ->label('')
                        ->prose()
                        ->extraAttributes(['style' => 'direction:rtl; text-align:right; white-space:pre-wrap; line-height:1.8']),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                    ->searchable()
                    ->weight(fn ($record) => $record->is_read
                        ? \Filament\Support\Enums\FontWeight::Normal
                        : \Filament\Support\Enums\FontWeight::Bold)
                    ->description(fn ($record) => $record->email),
                Tables\Columns\TextColumn::make('phone')
                    ->label('الهاتف')
                    ->copyable()
                    ->icon('heroicon-m-phone'),
                Tables\Columns\TextColumn::make('message')
                    ->label('الرسالة')
                    ->limit(80)
                    ->tooltip(fn ($record) => $record->message),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('الوقت')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->created_at->isToday() ? 'success' : 'gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('unread')
                    ->label('غير مقروءة فقط')
                    ->query(fn ($query) => $query->where('is_read', false))
                    ->toggle(),
                Tables\Filters\Filter::make('today')
                    ->label('اليوم فقط')
                    ->query(fn ($query) => $query->whereDate('created_at', today()))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_read')
                    ->label('تحديد كمقروء')
                    ->icon('heroicon-o-envelope-open')
                    ->color('success')
                    ->visible(fn ($record) => ! $record->is_read)
                    ->action(fn ($record) => $record->update(['is_read' => true])),
                Tables\Actions\ViewAction::make()->label('عرض'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\BulkAction::make('mark_all_read')
                    ->label('تحديد كمقروء')
                    ->icon('heroicon-o-envelope-open')
                    ->color('success')
                    ->action(fn ($records) => $records->each->update(['is_read' => true]))
                    ->deselectRecordsAfterCompletion(),
                Tables\Actions\DeleteBulkAction::make(),
            ])])
            ->emptyStateIcon('heroicon-o-envelope')
            ->emptyStateHeading('صندوق الرسائل فارغ')
            ->emptyStateDescription('ستظهر هنا رسائل العملاء من نموذج التواصل.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContacts::route('/'),
            'view'  => Pages\ViewContact::route('/{record}'),
        ];
    }
}
