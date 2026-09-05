<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentAccountResource\Pages;
use App\Models\PaymentAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Where the shop is paid, for the manual-transfer methods (handoff 13).
 *
 * These are real account numbers that real customers will transfer real money
 * to. The storefront currently ships placeholders that look genuine enough to
 * be paid into — replacing them here is the whole ticket.
 */
class PaymentAccountResource extends Resource
{
    protected static ?string $model = PaymentAccount::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'الطلبات';
    protected static ?string $navigationLabel = 'حسابات الدفع';
    protected static ?string $modelLabel = 'حساب دفع';
    protected static ?string $pluralModelLabel = 'حسابات الدفع';
    protected static ?int $navigationSort = 5;
    protected static ?string $slug = 'payment-accounts';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('الطريقة')->schema([
                Forms\Components\Select::make('method')
                    ->label('طريقة الدفع')
                    ->options(PaymentAccount::METHODS)
                    ->required()
                    // One account per method: the storefront looks the account
                    // up by method and would not know which of two to show.
                    ->unique(ignoreRecord: true)
                    ->live(),

                Forms\Components\TextInput::make('holder_name')
                    ->label('اسم صاحب الحساب')
                    ->required()
                    ->maxLength(190)
                    ->placeholder('شركة جلاسيه الأمير'),

                Forms\Components\TextInput::make('bank_name')
                    ->label('اسم البنك')
                    ->maxLength(190)
                    ->placeholder('بنك فلسطين')
                    // Wallets have no bank; handoff 13 says to leave it out.
                    ->helperText('للحسابات البنكية فقط — يُترك فارغاً للمحافظ')
                    ->visible(fn (Forms\Get $get) => $get('method') === 'bop'),

                Forms\Components\Toggle::make('active')->label('مفعّل')->default(true),
            ])->columns(2),

            Forms\Components\Section::make('بيانات التحويل')
                ->description('ما يراه الزبون وينسخه ليحوّل إليه')
                ->schema([
                    Forms\Components\TextInput::make('primary_label')
                        ->label('عنوان الحقل الأساسي')
                        ->required()
                        ->maxLength(60)
                        ->placeholder('رقم الحساب / رقم جوال باي'),

                    Forms\Components\TextInput::make('primary_value')
                        ->label('القيمة الأساسية')
                        ->required()
                        ->maxLength(190)
                        ->placeholder('123456789'),

                    Forms\Components\TextInput::make('secondary_label')
                        ->label('عنوان الحقل الثانوي')
                        ->maxLength(60)
                        ->placeholder('IBAN'),

                    Forms\Components\TextInput::make('secondary_value')
                        ->label('القيمة الثانوية')
                        ->maxLength(190)
                        ->placeholder('PS00PALS…'),
                ])->columns(2),

            Forms\Components\Section::make('رمز QR')->schema([
                Forms\Components\FileUpload::make('qr_image')
                    ->label('صورة الرمز')
                    ->image()
                    ->disk('public')
                    ->directory('payment-accounts')
                    ->maxSize(4096)
                    ->imageEditor()
                    ->helperText('يُعرض للزبون ليمسحه بتطبيق البنك أو المحفظة'),

                Forms\Components\TextInput::make('sort_order')
                    ->label('الترتيب')
                    ->numeric()
                    ->default(0),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('qr_image')->label('QR')->disk('public')->square(),

                Tables\Columns\TextColumn::make('method')
                    ->label('الطريقة')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => PaymentAccount::METHODS[$state] ?? $state),

                Tables\Columns\TextColumn::make('holder_name')->label('صاحب الحساب')->searchable(),

                Tables\Columns\TextColumn::make('primary_value')
                    ->label('الحساب')
                    ->copyable()
                    ->description(fn (PaymentAccount $record) => $record->primary_label),

                Tables\Columns\IconColumn::make('active')->label('مفعّل')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('لا توجد حسابات دفع')
            ->emptyStateDescription('أضف حساب المحل لكل طريقة تحويل يدوي يستخدمها الزبائن.');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPaymentAccounts::route('/'),
            'create' => Pages\CreatePaymentAccount::route('/create'),
            'edit'   => Pages\EditPaymentAccount::route('/{record}/edit'),
        ];
    }
}
