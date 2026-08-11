<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SizesRelationManager extends RelationManager
{
    protected static string $relationship = 'sizes';
    protected static ?string $title = 'الأحجام والأسعار (Builder)';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('معلومات الحجم')->schema([
                Forms\Components\TextInput::make('slug')
                    ->label('المعرف')
                    ->required()
                    ->maxLength(100)
                    ->helperText('مثال: cup-small · cup-medium · brad-large'),
                Forms\Components\TextInput::make('label')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(100)
                    ->helperText('مثال: صغير · وسط · كبير'),
                Forms\Components\TextInput::make('max_balls')
                    ->label('أقصى عدد كرات')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->helperText('0 = لا يوجد picker للنكهات (كالبراد)'),
                Forms\Components\TextInput::make('container_slug')
                    ->label('مخصص لحاوية (container_id)')
                    ->maxLength(100)
                    ->placeholder('اتركه فارغاً إذا ينطبق على جميع الحاويات')
                    ->helperText('مثال: cup · biscuit'),
                Forms\Components\FileUpload::make('image')
                    ->label('صورة الحجم')
                    ->image()
                    ->disk('public')
                    ->directory('sizes')
                    ->imagePreviewHeight('100')
                    ->maxSize(2048)
                    ->helperText('اختياري — يظهر بجانب صف الحجم في العائلي'),
                Forms\Components\Toggle::make('available')
                    ->label('متوفر')
                    ->default(true)
                    ->onColor('success')
                    ->offColor('danger')
                    ->helperText('غير المتوفر يظهر بشارة "غير متوفر" ولا يختفي'),
                Forms\Components\TextInput::make('sort_order')
                    ->label('الترتيب')
                    ->numeric()
                    ->default(1),
            ])->columns(2),

            Forms\Components\Section::make('شبكة الأسعار')
                ->description('أضف سعراً لكل عائلة نكهة متاحة في هذا الحجم')
                ->schema([
                    Forms\Components\Repeater::make('prices')
                        ->relationship()
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('flavor_family')
                                ->label('عائلة النكهة')
                                ->options([
                                    'classic' => '🍦 كلاسيك',
                                    'special' => '⭐ سبيشال',
                                    'mix'     => '🔀 مكس',
                                ])
                                ->required()
                                ->distinct(),
                            Forms\Components\TextInput::make('price')
                                ->label('السعر')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->suffix('₪'),
                        ])
                        ->columns(2)
                        ->addActionLabel('إضافة سعر')
                        ->reorderable(false)
                        ->defaultItems(0),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->label('المعرف')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('label')
                    ->label('الاسم')
                    ->weight(\Filament\Support\Enums\FontWeight::Medium),
                Tables\Columns\TextColumn::make('max_balls')
                    ->label('الكرات')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'primary' : 'gray'),
                Tables\Columns\TextColumn::make('container_slug')
                    ->label('الحاوية')
                    ->badge()
                    ->color('info')
                    ->placeholder('الكل'),
                Tables\Columns\ToggleColumn::make('available')
                    ->label('متوفر')
                    ->onColor('success')
                    ->offColor('danger'),
                Tables\Columns\TextColumn::make('prices_count')
                    ->label('أسعار مُضافة')
                    ->counts('prices')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([Tables\Actions\CreateAction::make()->label('إضافة حجم')])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('لا توجد أحجام')
            ->emptyStateDescription('أضف الأحجام مع أسعار عائلات النكهات.');
    }
}
