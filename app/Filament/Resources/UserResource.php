<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Who can sign into the dashboard, and as what.
 *
 * Two roles. The manager (مدير) sees everything: payment accounts, deleting a
 * shift, opening hours, staff. The accountant (محاسب) runs the counter —
 * orders, the cashier screen, refunds, drivers — without those.
 *
 * Only a manager reaches this page, and it will not let the shop lock itself
 * out: the last manager can neither be demoted nor deleted.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'الطلبات';
    protected static ?string $navigationLabel = 'مستخدمو اللوحة';
    protected static ?string $modelLabel = 'مستخدم';
    protected static ?string $pluralModelLabel = 'مستخدمو اللوحة';
    protected static ?int $navigationSort = 20;
    protected static ?string $slug = 'staff';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isManager() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny()
            && $record->getKey() !== auth()->id()
            && ! static::isLastManager($record);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('name')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(120),

                Forms\Components\TextInput::make('email')
                    ->label('البريد الإلكتروني (اسم الدخول)')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(190),

                Forms\Components\Select::make('role')
                    ->label('الصلاحية')
                    ->options(User::ROLES)
                    ->default(User::ROLE_ACCOUNTANT)
                    ->required()
                    ->native(false)
                    ->helperText('المدير: كل شيء. المحاسب: الطلبات والكاشير والسائقون، بدون حسابات الدفع وحذف الورديات والصلاحيات.')
                    // Demoting the only manager would leave nobody able to
                    // come back here and undo it.
                    ->rule(fn (?User $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                        if ($record && $value !== User::ROLE_MANAGER && static::isLastManager($record)) {
                            $fail('هذا آخر مدير — أضف مديراً آخر قبل تغيير صلاحيته.');
                        }
                    }),

                // Required when creating; left empty on edit it keeps the old
                // one. The model hashes it, so it is never stored as typed.
                Forms\Components\TextInput::make('password')
                    ->label(fn (string $operation) => $operation === 'create' ? 'كلمة السر' : 'كلمة سر جديدة (اتركها فارغة للإبقاء على الحالية)')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state)),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('email')->label('البريد')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('الصلاحية')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => User::ROLES[$state] ?? $state)
                    ->color(fn (?string $state) => $state === User::ROLE_MANAGER ? 'warning' : 'info'),
                Tables\Columns\TextColumn::make('created_at')->label('أُضيف')->date('d/m/Y'),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (User $record) => static::canDelete($record))
                    ->after(fn () => Notification::make()->title('تم حذف المستخدم')->success()->send()),
            ]);
    }

    public static function isLastManager(Model $record): bool
    {
        return $record->role === User::ROLE_MANAGER
            && User::where('role', User::ROLE_MANAGER)->count() <= 1;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
