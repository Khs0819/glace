<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HomeWhyGlaceResource\Pages;
use App\Models\HomeWhyGlace;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HomeWhyGlaceResource extends Resource
{
    protected static ?string $model = HomeWhyGlace::class;
    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $navigationGroup = 'الصفحة الرئيسية';
    protected static ?string $navigationLabel = 'قسم "لماذا جلاسيه"';
    protected static ?string $modelLabel = 'قسم لماذا جلاسيه';
    protected static ?string $pluralModelLabel = 'قسم لماذا جلاسيه';
    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('المحتوى الرئيسي')->schema([
                Forms\Components\TextInput::make('title')
                    ->label('العنوان')
                    ->required()
                    ->maxLength(300)
                    ->placeholder('لماذا جلاسيه الأمير؟'),
                Forms\Components\Textarea::make('description')
                    ->label('الوصف الداعم')
                    ->rows(2)
                    ->maxLength(500)
                    ->placeholder('جلاسيه الأمير حاصلة على شهادة الجودة العالمية ISO 22000'),
            ])->columns(1),

            Forms\Components\Section::make('الفيديو التعريفي')->schema([
                Forms\Components\TextInput::make('video_url')
                    ->label('رابط الفيديو (Embed)')
                    ->url()
                    ->suffixIcon('heroicon-m-play')
                    ->maxLength(500)
                    ->placeholder('https://www.youtube.com/embed/...'),
                Forms\Components\FileUpload::make('video_thumbnail')
                    ->label('صورة مصغرة للفيديو')
                    ->image()
                    ->disk('public')
                    ->directory('why-glace')
                    ->imagePreviewHeight('100')
                    ->maxSize(2048),
            ])->columns(2)->aside(),

            Forms\Components\Section::make('المميزات')
                ->description('كل ميزة تظهر كبطاقة في القسم — أضف عنواناً وصورة لكل واحدة')
                ->schema([
                    Forms\Components\Repeater::make('features')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('label')
                                ->label('عنوان الميزة')
                                ->required()
                                ->maxLength(100)
                                ->placeholder('جودة عالية'),
                            Forms\Components\FileUpload::make('image')
                                ->label('صورة الميزة')
                                ->image()
                                ->disk('public')
                                ->directory('why-glace')
                                ->imagePreviewHeight('80')
                                ->maxSize(2048),
                        ])
                        ->columns(2)
                        ->reorderableWithDragAndDrop()
                        ->addActionLabel('إضافة ميزة')
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? 'ميزة جديدة')
                        ->defaultItems(0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('العنوان'),
                Tables\Columns\TextColumn::make('updated_at')->label('آخر تعديل')->dateTime('d/m/Y H:i'),
            ])
            ->actions([Tables\Actions\EditAction::make()->label('تعديل')]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListHomeWhyGlaces::route('/'),
            'create' => Pages\CreateHomeWhyGlace::route('/create'),
            'edit'   => Pages\EditHomeWhyGlace::route('/{record}/edit'),
        ];
    }
}
