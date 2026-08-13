<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HeroSlideResource\Pages;
use App\Models\HeroSlide;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HeroSlideResource extends Resource
{
    protected static ?string $model = HeroSlide::class;
    protected static ?string $navigationIcon = 'heroicon-o-photo';
    protected static ?string $navigationGroup = 'الصفحة الرئيسية';
    protected static ?string $navigationLabel = 'سلايدر الرئيسية';
    protected static ?string $modelLabel = 'سلايد';
    protected static ?string $pluralModelLabel = 'سلايدات الرئيسية';
    protected static ?int $navigationSort = 10;

    /**
     * `/home` skips slides that are missing any of the three images, because
     * `ISlideData` requires all of them (handoff 08 §أ-4). Flag those here so
     * an admin can see why a slide is not on the site.
     */
    public static function getNavigationBadge(): ?string
    {
        $incomplete = static::getModel()::query()
            ->where(fn ($q) => $q->whereNull('man_img')->orWhereNull('piece_img')->orWhereNull('zigzags_img'))
            ->count();

        return $incomplete > 0 ? $incomplete . ' غير مكتمل' : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('النصوص')->schema([
                Forms\Components\TextInput::make('title_h1')
                    ->label('العنوان الرئيسي (H1)')
                    ->required()
                    ->maxLength(200)
                    ->placeholder('جلاسية الأمير'),
                Forms\Components\TextInput::make('title_h2')
                    ->label('العنوان الفرعي (H2)')
                    ->required()
                    ->maxLength(300)
                    ->placeholder('لإنتاج الآيس كريم و البراد و العصائر'),
                Forms\Components\TextInput::make('sort_order')
                    ->label('الترتيب')
                    ->numeric()
                    ->default(1)
                    ->minValue(1),
            ])->columns(2),

            Forms\Components\Section::make('الصور')
                ->description('صور السلايد — ارفع صورة الشخصية وقطعة الآيس كريم وزخرفة الزيجزاج')
                ->schema([
                    Forms\Components\FileUpload::make('man_img')
                        ->label('صورة الشخصية')
                        ->image()
                        ->disk('public')
                        ->directory('hero-slides')
                        ->imagePreviewHeight('120')
                        ->maxSize(2048)
                        ->required(),
                    Forms\Components\FileUpload::make('piece_img')
                        ->label('صورة قطعة الآيس كريم')
                        ->image()
                        ->disk('public')
                        ->directory('hero-slides')
                        ->imagePreviewHeight('120')
                        ->maxSize(2048)
                        ->required(),
                    Forms\Components\FileUpload::make('zigzags_img')
                        ->label('صورة الزيجزاج (ديكور)')
                        ->image()
                        ->disk('public')
                        ->directory('hero-slides')
                        ->imagePreviewHeight('120')
                        ->maxSize(2048)
                        ->required(),
                ])->columns(3),

            Forms\Components\Section::make('الألوان')
                ->description('ألوان الخلفية والعناوين — تحدد هوية كل سلايد بصرياً')
                ->schema([
                    Forms\Components\ColorPicker::make('bg_color')
                        ->label('لون الخلفية')
                        ->required(),
                    Forms\Components\ColorPicker::make('header_bg_color')
                        ->label('لون خلفية الهيدر')
                        ->required(),
                    Forms\Components\ColorPicker::make('h1_bg_color')
                        ->label('لون خلفية H1')
                        ->required(),
                    Forms\Components\ColorPicker::make('h2_bg_color')
                        ->label('لون خلفية H2')
                        ->required(),
                ])->columns(4),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('man_img')
                    ->label('الشخصية')
                    ->disk('public')
                    ->square()
                    ->size(48)
                    ->defaultImageUrl('https://placehold.co/48x48/e2e8f0/64748b?text=%E2%80%94'),
                Tables\Columns\ColorColumn::make('bg_color')
                    ->label('اللون')
                    ->width(50),
                Tables\Columns\TextColumn::make('title_h1')
                    ->label('العنوان الرئيسي')
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->getStateUsing(fn (HeroSlide $record) => $record->man_img && $record->piece_img && $record->zigzags_img
                        ? 'ظاهر'
                        : 'مخفي — صور ناقصة')
                    ->color(fn (string $state) => $state === 'ظاهر' ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('title_h2')
                    ->label('العنوان الفرعي')
                    ->limit(50)
                    ->color('gray'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ])])
            ->emptyStateHeading('لا توجد سلايدات')
            ->emptyStateDescription('أضف سلايدات لسلايدر الصفحة الرئيسية.');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListHeroSlides::route('/'),
            'create' => Pages\CreateHeroSlide::route('/create'),
            'edit'   => Pages\EditHeroSlide::route('/{record}/edit'),
        ];
    }
}
