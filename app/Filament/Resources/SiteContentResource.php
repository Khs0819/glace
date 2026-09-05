<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiteContentResource\Pages;
use App\Models\SiteContent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The long-form legal pages: terms (16) and privacy (17).
 *
 * The body is HTML the storefront renders through dangerouslySetInnerHTML. It
 * is sanitised on save by SiteContent's mutator and again on read, and the
 * storefront runs DOMPurify over it independently — neither side treats the
 * other's pass as sufficient.
 */
class SiteContentResource extends Resource
{
    protected static ?string $model = SiteContent::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'المحتوى';
    protected static ?string $navigationLabel = 'الصفحات القانونية';
    protected static ?string $modelLabel = 'صفحة';
    protected static ?string $pluralModelLabel = 'الصفحات القانونية';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?string $slug = 'site-contents';

    /** The two pages are fixed; the storefront has a route for each. */
    public static function canCreate(): bool
    {
        return SiteContent::count() < 2;
    }

    public static function canDelete(Model $record): bool
    {
        // Deleting one would leave its storefront page blank with no way back
        // except a new row with exactly the right key.
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('الصفحة')->schema([
                Forms\Components\Select::make('key')
                    ->label('الصفحة')
                    ->options([
                        SiteContent::KEY_TERMS   => 'الشروط والأحكام',
                        SiteContent::KEY_PRIVACY => 'سياسة الخصوصية',
                    ])
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit'),

                Forms\Components\TextInput::make('title')
                    ->label('العنوان')
                    ->required()
                    ->maxLength(190),
            ])->columns(2),

            Forms\Components\Section::make('المحتوى')
                ->description('يُسمح فقط بـ h3 · p · ul/li · a — وأي شيء آخر يُزال تلقائياً عند الحفظ')
                ->schema([
                    Forms\Components\RichEditor::make('body')
                        ->label('النص')
                        ->required()
                        ->columnSpanFull()
                        // Matches HtmlSanitizer's allowlist. The editor limits
                        // what can be typed; the sanitiser is what actually
                        // enforces it, including on a pasted payload.
                        ->toolbarButtons([
                            'h3', 'bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo',
                        ])
                        ->helperText('يمر المحتوى عبر تنقية أمنية عند الحفظ وعند العرض.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('العنوان')->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                Tables\Columns\TextColumn::make('key')->label('المعرف')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('updated_at')->label('آخر تعديل')->dateTime('d/m/Y — H:i'),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->emptyStateHeading('لا توجد صفحات')
            ->emptyStateDescription('أضف صفحتي الشروط والأحكام وسياسة الخصوصية.');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSiteContents::route('/'),
            'create' => Pages\CreateSiteContent::route('/create'),
            'edit'   => Pages\EditSiteContent::route('/{record}/edit'),
        ];
    }
}
