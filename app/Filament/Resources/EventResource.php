<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Filament\Resources\EventResource\RelationManagers;
use App\Models\Event;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = 'الفعاليات';
    protected static ?string $navigationLabel = 'الفعاليات';
    protected static ?string $modelLabel = 'فعالية';
    protected static ?string $pluralModelLabel = 'الفعاليات';
    protected static ?int $navigationSort = 20;
    protected static ?string $recordTitleAttribute = 'title';

    /**
     * `IEvent.listImage` must not be null for published events (handoff 04 §3),
     * so surface the backlog of events still missing a card image.
     */
    public static function getNavigationBadge(): ?string
    {
        $missing = static::getModel()::whereNull('list_image')->count();

        return $missing > 0 ? $missing . ' بلا صورة' : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('معلومات الفعالية')->schema([
                Forms\Components\TextInput::make('title')
                    ->label('عنوان الفعالية')
                    ->required()
                    ->maxLength(300)
                    ->columnSpanFull(),
                Forms\Components\DatePicker::make('date')
                    ->label('تاريخ الفعالية')
                    ->required()
                    ->displayFormat('d/m/Y')
                    ->native(false),
                Forms\Components\FileUpload::make('list_image')
                    ->label('صورة البطاقة')
                    ->image()
                    ->disk('public')
                    ->directory('events')
                    ->imagePreviewHeight('150')
                    ->maxSize(2048)
                    ->required()
                    ->helperText('تظهر في قائمة الفعاليات — يُنصح بأبعاد 400×300px')
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('الوصف التفصيلي')
                ->description('يظهر في صفحة تفاصيل الفعالية')
                ->schema([
                    Forms\Components\RichEditor::make('description')
                        ->label('')
                        ->required()
                        ->toolbarButtons([
                            'bold', 'italic', 'underline',
                            'bulletList', 'orderedList',
                            'h2', 'h3',
                            'redo', 'undo',
                        ])
                        ->extraInputAttributes(['dir' => 'rtl', 'style' => 'min-height:200px'])
                        ->fileAttachmentsDisk('public'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('list_image')
                    ->label('')
                    ->disk('public')
                    ->square()
                    ->size(56)
                    ->defaultImageUrl('https://placehold.co/56x56/f59e0b/ffffff?text=📅'),
                Tables\Columns\TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->limit(50)
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                Tables\Columns\TextColumn::make('date')
                    ->label('التاريخ')
                    ->date('d/m/Y')
                    ->sortable()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('images_count')
                    ->label('الصور')
                    ->counts('images')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('أُضيف')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color('gray'),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\Filter::make('missing_list_image')
                    ->label('بدون صورة بطاقة')
                    ->query(fn (Builder $query) => $query->whereNull('list_image'))
                    ->toggle(),
                Tables\Filters\Filter::make('missing_gallery')
                    ->label('بدون معرض صور')
                    ->query(fn (Builder $query) => $query->doesntHave('images'))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ])]);
    }

    public static function getRelationManagers(): array
    {
        return [
            RelationManagers\EventImagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit'   => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}
