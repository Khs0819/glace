<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BranchResource\Pages;
use App\Models\Branch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'الصفحة الرئيسية';
    protected static ?string $navigationLabel = 'الفروع';
    protected static ?string $modelLabel = 'فرع';
    protected static ?string $pluralModelLabel = 'الفروع';
    protected static ?int $navigationSort = 13;
    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Branch')->tabs([
                Forms\Components\Tabs\Tab::make('المعلومات الأساسية')
                    ->icon('heroicon-o-building-storefront')
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label('المعرف (Slug)')
                            ->required()
                            ->maxLength(100)
                            ->disabled(fn ($record) => $record !== null)
                            ->dehydrated()
                            ->helperText('مثال: ramal · nuseirat'),
                        Forms\Components\TextInput::make('label')
                            ->label('اسم الفرع')
                            ->required()
                            ->maxLength(200)
                            ->placeholder('فرع الرمال'),
                        Forms\Components\Textarea::make('address')
                            ->label('العنوان الكامل')
                            ->required()
                            ->rows(2)
                            ->maxLength(500)
                            ->placeholder('غزة، الرمال، شارع الشهداء...')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('الترتيب')
                            ->numeric()
                            ->default(1),
                    ])->columns(2),

                Forms\Components\Tabs\Tab::make('التواصل وأوقات العمل')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('رقم الهاتف')
                            ->tel()
                            ->required()
                            ->maxLength(50)
                            ->placeholder('0592 226 522')
                            ->prefixIcon('heroicon-m-phone'),
                        Forms\Components\TextInput::make('whatsapp')
                            ->label('رقم واتساب')
                            ->tel()
                            ->maxLength(50)
                            ->placeholder('0592 226 522')
                            ->prefixIcon('heroicon-m-chat-bubble-left'),
                        Forms\Components\TextInput::make('weekday_hours')
                            ->label('أوقات الأيام العادية (ش – خ)')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('PM 11:45 – AM 10:00')
                            ->prefixIcon('heroicon-m-sun'),
                        Forms\Components\TextInput::make('friday_hours')
                            ->label('أوقات يوم الجمعة')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('PM 11:45 – PM 02:00')
                            ->prefixIcon('heroicon-m-moon'),
                    ])->columns(2),

                Forms\Components\Tabs\Tab::make('الخريطة')
                    ->icon('heroicon-o-map')
                    ->schema([
                        Forms\Components\Textarea::make('map_src')
                            ->label('رابط تضمين خريطة جوجل (Embed URL)')
                            ->required()
                            ->rows(3)
                            ->placeholder('https://www.google.com/maps/embed?pb=...')
                            ->helperText('من Google Maps → Share → Embed a map → انسخ الـ src'),
                        Forms\Components\TextInput::make('border_radius')
                            ->label('شكل إطار الخريطة (CSS border-radius)')
                            ->maxLength(200)
                            ->placeholder('32% 68% 69% 31% / 30% 28% 72% 70%')
                            ->helperText('تحكم في شكل إطار الخريطة بالـ CSS'),
                    ]),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Slug')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('label')
                    ->label('الفرع')
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold)
                    ->description(fn ($record) => $record->address ? \Str::limit($record->address, 50) : null),
                Tables\Columns\TextColumn::make('phone')
                    ->label('الهاتف')
                    ->copyable()
                    ->icon('heroicon-m-phone'),
                Tables\Columns\TextColumn::make('weekday_hours')
                    ->label('الأوقات العادية')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'edit'   => Pages\EditBranch::route('/{record}/edit'),
        ];
    }
}
