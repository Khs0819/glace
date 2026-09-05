<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Discount codes (handoff 11).
 *
 * These used to live in the storefront bundle, where every code in the shop was
 * one devtools window away from any customer. Editing them here is the point of
 * the whole ticket.
 */
class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationGroup = 'الطلبات';
    protected static ?string $navigationLabel = 'كوبونات الخصم';
    protected static ?string $modelLabel = 'كوبون';
    protected static ?string $pluralModelLabel = 'كوبونات الخصم';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('الكود والقيمة')->schema([
                Forms\Components\TextInput::make('code')
                    ->label('الكود')
                    ->required()
                    ->maxLength(40)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    // Stored upper-case, so "glace10" and "GLACE10" cannot
                    // become two different coupons.
                    ->helperText('يُقارن بدون حساسية لحالة الأحرف — مثال: GLACE10')
                    ->placeholder('GLACE10'),

                Forms\Components\ToggleButtons::make('type')
                    ->label('نوع الخصم')
                    ->options([
                        Coupon::TYPE_FIXED   => 'مبلغ ثابت (₪)',
                        Coupon::TYPE_PERCENT => 'نسبة مئوية (%)',
                    ])
                    ->default(Coupon::TYPE_FIXED)
                    ->inline()
                    ->live()
                    ->required(),

                Forms\Components\TextInput::make('value')
                    ->label(fn (Forms\Get $get) => $get('type') === Coupon::TYPE_PERCENT ? 'النسبة' : 'قيمة الخصم')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->maxValue(fn (Forms\Get $get) => $get('type') === Coupon::TYPE_PERCENT ? 100 : 10000)
                    ->suffix(fn (Forms\Get $get) => $get('type') === Coupon::TYPE_PERCENT ? '%' : '₪'),

                Forms\Components\TextInput::make('max_discount')
                    ->label('الحد الأقصى للخصم')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('₪')
                    // Without this, "50% off" is unbounded on a large order.
                    ->helperText('للنسبة المئوية فقط — يمنع خصماً مفتوحاً على طلب كبير')
                    ->visible(fn (Forms\Get $get) => $get('type') === Coupon::TYPE_PERCENT),
            ])->columns(2),

            Forms\Components\Section::make('شروط الاستخدام')
                ->description('اتركها فارغة إذا لم يكن هناك شرط')
                ->schema([
                    Forms\Components\TextInput::make('min_subtotal')
                        ->label('الحد الأدنى للطلب')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('₪'),

                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label('تاريخ الانتهاء')
                        ->seconds(false)
                        ->native(false),

                    Forms\Components\TextInput::make('usage_limit')
                        ->label('عدد مرات الاستخدام الكلي')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('لكل الزبائن مجتمعين'),

                    Forms\Components\TextInput::make('per_customer_limit')
                        ->label('عدد المرات لكل زبون')
                        ->numeric()
                        ->minValue(1),
                ])->columns(2),

            Forms\Components\Section::make('الحالة')->schema([
                Forms\Components\Toggle::make('active')
                    ->label('مفعّل')
                    ->default(true),

                Forms\Components\TextInput::make('used_count')
                    ->label('عدد مرات الاستخدام')
                    ->numeric()
                    // A record of what happened, not a dial: editing it would
                    // hand out redemptions that were already spent.
                    ->disabled()
                    ->dehydrated(false),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('الكود')
                    ->searchable()
                    ->copyable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold),

                Tables\Columns\TextColumn::make('value')
                    ->label('الخصم')
                    ->formatStateUsing(fn (Coupon $record) => $record->type === Coupon::TYPE_PERCENT
                        ? rtrim(rtrim(number_format($record->value, 2), '0'), '.') . '%'
                        : rtrim(rtrim(number_format($record->value, 2), '0'), '.') . ' ₪'),

                Tables\Columns\TextColumn::make('min_subtotal')
                    ->label('حد أدنى')
                    ->suffix(' ₪')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('used_count')
                    ->label('استُخدم')
                    ->formatStateUsing(fn (Coupon $record) => $record->usage_limit === null
                        ? (string) $record->used_count
                        : "{$record->used_count} / {$record->usage_limit}"),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('ينتهي')
                    ->dateTime('d/m/Y')
                    ->placeholder('لا ينتهي')
                    ->color(fn (Coupon $record) => $record->expired() ? 'danger' : null),

                Tables\Columns\IconColumn::make('active')
                    ->label('مفعّل')
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('active')->label('مفعّل'),

                Tables\Filters\Filter::make('usable')
                    ->label('صالح الآن')
                    ->query(fn ($query) => $query
                        ->where('active', true)
                        ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('لا توجد كوبونات')
            ->emptyStateDescription('أضف كوبوناً ليستخدمه الزبائن عند الدفع.');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit'   => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
