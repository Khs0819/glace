<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EventImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';
    protected static ?string $title = 'معرض صور الفعالية';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\FileUpload::make('image_url')
                ->label('صورة الفعالية')
                ->image()
                ->disk('public')
                ->directory('event-images')
                ->imagePreviewHeight('150')
                ->maxSize(2048)
                ->required()
                ->columnSpanFull(),
            Forms\Components\TextInput::make('sort_order')
                ->label('الترتيب')
                ->numeric()
                ->default(1)
                ->minValue(1),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('الصورة')
                    ->disk('public')
                    ->width(120)
                    ->height(80)
                    ->extraImgAttributes(['class' => 'rounded-lg object-cover']),
                Tables\Columns\TextColumn::make('image_url')
                    ->label('مسار الملف')
                    ->limit(50)
                    ->color('gray'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('إضافة صورة'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('لا توجد صور')
            ->emptyStateDescription('أضف صور المعرض التي ستظهر في صفحة تفاصيل الفعالية.');
    }
}
