<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FaqResource\Pages;
use App\Models\Faq;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * The help page's questions (handoff 15) — moved out of the bundle so support
 * can reword an answer without a deploy.
 */
class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static ?string $navigationGroup = 'المحتوى';
    protected static ?string $navigationLabel = 'الأسئلة الشائعة';
    protected static ?string $modelLabel = 'سؤال';
    protected static ?string $pluralModelLabel = 'الأسئلة الشائعة';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'question';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('السؤال والجواب')->schema([
                Forms\Components\TextInput::make('question')
                    ->label('السؤال')
                    ->required()
                    ->maxLength(190)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?string $state) {
                        // The id is the anchor the storefront opens the
                        // accordion at, so it is only derived for a new row —
                        // changing it later would break a shared link.
                        if (blank($get('id')) && filled($state)) {
                            $set('id', Str::slug(Str::limit($state, 50, '')) ?: 'faq-' . Str::random(6));
                        }
                    }),

                Forms\Components\TextInput::make('id')
                    ->label('المعرف')
                    ->required()
                    ->maxLength(60)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit')
                    ->helperText('يُستخدم كرابط مباشر للسؤال — لا يتغيّر بعد الإنشاء'),

                Forms\Components\Textarea::make('answer')
                    ->label('الجواب')
                    ->required()
                    ->rows(6)
                    ->columnSpanFull()
                    // The rendering rule the storefront applies, stated where
                    // the person writing the answer will actually read it.
                    ->helperText('افصل الفقرات بسطر فارغ. فقرة تبدأ بـ"- " تُعرض كقائمة نقطية.'),
            ])->columns(2),

            Forms\Components\Section::make('رابط اختياري')
                ->description('يظهر في آخر الجواب — مثل رابط صفحة التواصل')
                ->schema([
                    Forms\Components\TextInput::make('link_href')
                        ->label('الرابط')
                        ->maxLength(190)
                        ->placeholder('/contact'),

                    Forms\Components\TextInput::make('link_label')
                        ->label('نص الرابط')
                        ->maxLength(60)
                        ->placeholder('تواصل معنا')
                        ->requiredWith('link_href'),
                ])->columns(2),

            Forms\Components\Section::make('العرض')->schema([
                Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
                Forms\Components\Toggle::make('active')->label('ظاهر')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('question')
                    ->label('السؤال')
                    ->searchable()
                    ->wrap()
                    ->limit(80),

                Tables\Columns\TextColumn::make('id')->label('المعرف')->badge()->color('gray'),

                Tables\Columns\IconColumn::make('link_href')
                    ->label('رابط')
                    ->getStateUsing(fn (Faq $record) => $record->link_href !== null)
                    ->boolean(),

                Tables\Columns\IconColumn::make('active')->label('ظاهر')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('لا توجد أسئلة')
            ->emptyStateDescription('أضف الأسئلة التي تظهر في صفحة المساعدة.');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFaqs::route('/'),
            'create' => Pages\CreateFaq::route('/create'),
            'edit'   => Pages\EditFaq::route('/{record}/edit'),
        ];
    }
}
