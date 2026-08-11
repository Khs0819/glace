<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HomeAboutResource\Pages;
use App\Models\HomeAbout;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HomeAboutResource extends Resource
{
    protected static ?string $model = HomeAbout::class;
    protected static ?string $navigationIcon = 'heroicon-o-information-circle';
    protected static ?string $navigationGroup = 'الصفحة الرئيسية';
    protected static ?string $navigationLabel = 'قسم "عن الشركة"';
    protected static ?string $modelLabel = 'قسم عن الشركة';
    protected static ?string $pluralModelLabel = 'قسم عن الشركة';
    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('العنوان والصورة')->schema([
                Forms\Components\TextInput::make('title')
                    ->label('العنوان الرئيسي')
                    ->required()
                    ->maxLength(300)
                    ->placeholder('موهوبون في صناعة الأيسكريم !'),
                Forms\Components\FileUpload::make('image')
                    ->label('صورة الشخصية')
                    ->image()
                    ->disk('public')
                    ->directory('about')
                    ->imagePreviewHeight('150')
                    ->maxSize(2048),
            ])->columns(2),

            Forms\Components\Section::make('الفقرات النصية')
                ->description('أضف فقرة واحدة أو أكثر تظهر في قسم "عن الشركة"')
                ->schema([
                    Forms\Components\Repeater::make('paragraphs')
                        ->label('')
                        ->schema([
                            Forms\Components\Textarea::make('text')
                                ->label('نص الفقرة')
                                ->required()
                                ->rows(3)
                                ->extraInputAttributes(['dir' => 'rtl']),
                        ])
                        ->reorderableWithDragAndDrop()
                        ->addActionLabel('إضافة فقرة')
                        ->defaultItems(1)
                        ->minItems(1)
                        ->collapsible()
                        ->cloneable()
                        ->itemLabel(fn (array $state): ?string => isset($state['text']) ? \Str::limit($state['text'], 60) : 'فقرة جديدة'),
                ]),

            Forms\Components\Section::make('زر الإجراء (CTA)')->schema([
                Forms\Components\TextInput::make('cta_label')
                    ->label('نص الزر')
                    ->maxLength(100)
                    ->placeholder('اعرف أكثر'),
                Forms\Components\TextInput::make('cta_href')
                    ->label('رابط الزر')
                    ->maxLength(200)
                    ->placeholder('/about')
                    ->prefix('/'),
            ])->columns(2)->aside(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('العنوان')
                    ->limit(60),
                Tables\Columns\TextColumn::make('cta_label')
                    ->label('نص زر الإجراء')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('آخر تعديل')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([Tables\Actions\EditAction::make()->label('تعديل')]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListHomeAbouts::route('/'),
            'create' => Pages\CreateHomeAbout::route('/create'),
            'edit'   => Pages\EditHomeAbout::route('/{record}/edit'),
        ];
    }
}
