<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\Payment;
use App\Services\Checkout\OrderPaymentService;
use App\Services\JawwalPay\JawwalPayException;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Every charge attempt against this order, and the two things an admin can do
 * about one that never got an answer: ask the provider what happened, then
 * close it by hand.
 *
 * Nothing here starts a payment. Charges are raised by the customer from the
 * storefront; this is the record and the recovery path.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';
    protected static ?string $title = 'محاولات الدفع';

    public function form(Form $form): Form
    {
        return $form->schema([/* read-only — see the table and its actions */]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('الوقت')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('wallet')
                    ->label('المحفظة')
                    // Never the full number on screen.
                    ->formatStateUsing(fn (Payment $record) => $record->maskedWallet()),
                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->suffix(' ₪'),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (Payment $record) => $record->statusLabel())
                    ->color(fn (string $state): string => match ($state) {
                        Payment::STATUS_PAID       => 'success',
                        Payment::STATUS_OTP_SENT   => 'info',
                        Payment::STATUS_UNRESOLVED => 'danger',
                        Payment::STATUS_FAILED     => 'warning',
                        default                    => 'gray',
                    })
                    ->icon(fn (string $state): ?string => $state === Payment::STATUS_UNRESOLVED
                        ? 'heroicon-o-exclamation-triangle'
                        : null),
                Tables\Columns\TextColumn::make('error_code')
                    ->label('رد البوابة')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->formatStateUsing(fn (Payment $record) => $record->errorLabel()
                        ? $record->error_code . ' — ' . $record->errorLabel()
                        : $record->error_code)
                    ->tooltip(fn (Payment $record) => $record->errorMessage()),
                Tables\Columns\TextColumn::make('confirm_attempts')
                    ->label('محاولات الرمز')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state) => $state >= Payment::MAX_CONFIRM_ATTEMPTS ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('provider_reference')
                    ->label('مرجع البوابة')
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(Payment::STATUSES),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('lookup')
                    ->label('استعلام من البوابة')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('info')
                    ->visible(fn (Payment $record) => $record->needsReview())
                    ->action(function (Payment $record) {
                        try {
                            $result = app(OrderPaymentService::class)->lookup($record);
                        } catch (JawwalPayException $e) {
                            Notification::make()
                                ->title('تعذّر الاستعلام')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title($result['found'] > 0
                                ? "وُجدت {$result['found']} عملية بمرجع هذا الطلب"
                                : 'لم تُوجد أي عملية بمرجع هذا الطلب')
                            ->body($result['found'] > 0
                                ? 'يبدو أن المبلغ خُصم فعلاً — راجع كشف الحساب ثم أغلق المحاولة كـ«مدفوع».'
                                : 'لم يُخصم المبلغ على الأرجح — يمكن إغلاق المحاولة كـ«فشل».')
                            ->color($result['found'] > 0 ? 'success' : 'warning')
                            ->persistent()
                            ->send();
                    }),

                Tables\Actions\Action::make('resolve')
                    ->label('إغلاق المحاولة')
                    ->icon('heroicon-o-check-badge')
                    ->color('warning')
                    ->visible(fn (Payment $record) => $record->needsReview())
                    ->requiresConfirmation()
                    ->modalHeading('تسوية يدوية لمحاولة دفع غير مؤكدة')
                    ->modalDescription('استعلم من البوابة أولاً. هذا الإجراء يغيّر حالة الطلب.')
                    ->form([
                        Forms\Components\Radio::make('outcome')
                            ->label('ما الذي حدث فعلاً؟')
                            ->options([
                                'paid'   => 'تم الخصم — اعتبر الطلب مدفوعاً',
                                'failed' => 'لم يتم الخصم — اعتبر المحاولة فاشلة',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('note')
                            ->label('سبب التسوية')
                            ->placeholder('مثال: ظهرت العملية في كشف الحساب بمرجع 2921218715102')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (Payment $record, array $data) {
                        app(OrderPaymentService::class)
                            ->resolveManually($record, $data['outcome'] === 'paid', $data['note']);

                        Notification::make()
                            ->title('تم إغلاق المحاولة')
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('لا توجد محاولات دفع')
            ->emptyStateDescription('تظهر هنا كل محاولة دفع عبر جوال باي لهذا الطلب.');
    }
}
