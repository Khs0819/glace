<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use App\Services\Checkout\Money;
use App\Services\Storefront\WalletService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Storefront accounts (handoff 08 · 09).
 *
 * Read-mostly. These are not `users` — they cannot sign into this dashboard,
 * and there is no password to reset because the system has none.
 */
class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'الزبائن';
    protected static ?string $navigationLabel = 'الزبائن';
    protected static ?string $modelLabel = 'زبون';
    protected static ?string $pluralModelLabel = 'الزبائن';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'name';

    /** @return array<int, string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone', 'email'];
    }

    /** Accounts are created by signing in with a phone number, not from here. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        // Orders and wallet history hang off this row; deleting it would take
        // the shop's own records with it.
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('الحساب')->schema([
                Forms\Components\TextInput::make('name')->label('الاسم')->required()->maxLength(120),

                Forms\Components\TextInput::make('phone')
                    ->label('الهاتف')
                    ->required()
                    ->maxLength(15)
                    ->unique(ignoreRecord: true)
                    // It is the login identity, not a contact detail.
                    ->helperText('رقم الدخول للحساب — تغييره يغيّر الرقم الذي يستقبل رمز التحقق'),

                Forms\Components\TextInput::make('email')
                    ->label('البريد الإلكتروني')
                    ->email()
                    ->maxLength(190)
                    ->unique(ignoreRecord: true)
                    ->helperText('اختياري — كثير من الحسابات بدون بريد'),

                Forms\Components\Toggle::make('blocked')
                    ->label('موقوف')
                    ->helperText('يمنع تسجيل الدخول ويُبطل الجلسات الحالية'),
            ])->columns(2),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('الحساب')->schema([
                Infolists\Components\TextEntry::make('name')->label('الاسم'),
                Infolists\Components\TextEntry::make('phone')->label('الهاتف')->copyable()->icon('heroicon-m-phone'),
                Infolists\Components\TextEntry::make('email')->label('البريد')->placeholder('—'),
                Infolists\Components\TextEntry::make('created_at')->label('انضم')->dateTime('d/m/Y'),
                Infolists\Components\TextEntry::make('last_login_at')->label('آخر دخول')
                    ->dateTime('d/m/Y — H:i')->placeholder('—'),
                Infolists\Components\IconEntry::make('blocked')->label('موقوف')->boolean(),
            ])->columns(3),

            Infolists\Components\Section::make('المحفظة')->schema([
                Infolists\Components\TextEntry::make('wallet.balance')
                    ->label('الرصيد')
                    ->suffix(' ₪')
                    ->placeholder('0.00')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                Infolists\Components\TextEntry::make('orders_count')
                    ->label('عدد الطلبات')
                    ->state(fn (Customer $record) => $record->orders()->count()),

                Infolists\Components\TextEntry::make('orders_total')
                    ->label('إجمالي المشتريات')
                    ->suffix(' ₪')
                    ->state(fn (Customer $record) => number_format((float) $record->orders()->sum('total'), 2)),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable()
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold),

                Tables\Columns\TextColumn::make('phone')->label('الهاتف')->searchable()->copyable(),

                Tables\Columns\TextColumn::make('email')->label('البريد')->searchable()->placeholder('—')->toggleable(),

                Tables\Columns\TextColumn::make('orders_count')->label('طلبات')->counts('orders')->badge()->color('gray'),

                Tables\Columns\TextColumn::make('wallet.balance')->label('الرصيد')->suffix(' ₪')->placeholder('0.00'),

                Tables\Columns\TextColumn::make('created_at')->label('انضم')->date('d/m/Y')->sortable(),

                Tables\Columns\IconColumn::make('blocked')->label('موقوف')->boolean()
                    ->trueColor('danger')->falseColor('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([Tables\Filters\TernaryFilter::make('blocked')->label('موقوف')])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('adjustWallet')
                    ->label('تعديل الرصيد')
                    ->icon('heroicon-o-banknotes')
                    ->color('warning')
                    ->form([
                        Forms\Components\ToggleButtons::make('direction')
                            ->label('العملية')
                            ->options(['credit' => 'إضافة رصيد', 'debit' => 'خصم رصيد'])
                            ->default('credit')
                            ->inline()
                            ->required(),

                        Forms\Components\TextInput::make('amount')
                            ->label('المبلغ')->numeric()->required()->minValue(0.01)->suffix('₪'),

                        Forms\Components\TextInput::make('label')
                            ->label('السبب')
                            ->required()
                            ->maxLength(190)
                            // It appears verbatim on the customer's statement.
                            ->helperText('يظهر للزبون في سجل معاملاته')
                            ->placeholder('تعويض عن طلب ملغي'),
                    ])
                    ->action(function (Customer $record, array $data) {
                        $wallet = app(WalletService::class);
                        $amount = Money::toAgorot($data['amount']);

                        try {
                            $data['direction'] === 'credit'
                                ? $wallet->credit($record, $amount, $data['label'])
                                : $wallet->debit($record, $amount, $data['label']);
                        } catch (\RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('تم تعديل الرصيد')->success()->send();
                    }),
            ])
            ->emptyStateHeading('لا يوجد زبائن بعد')
            ->emptyStateDescription('تُنشأ الحسابات تلقائياً عند أول تسجيل دخول من التطبيق.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'view'  => Pages\ViewCustomer::route('/{record}'),
            'edit'  => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
