<?php

namespace App\Filament\Pages;

use App\Models\StoreSetting;
use App\Models\User;
use App\Services\Storefront\StoreHours;
use Carbon\CarbonImmutable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/**
 * Opening hours for the shop and for delivery, the manual overrides, and the
 * signed-in user's own profile.
 *
 * Hours and overrides decide whether customers can order at all, so only a
 * manager may change them. Everyone can see the current state and edit their
 * own profile.
 */
class StoreSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'إعدادات المتجر';
    protected static ?string $navigationGroup = 'الطلبات';
    protected static ?string $title           = 'إعدادات المتجر';
    protected static ?int    $navigationSort  = 10;

    protected static string $view = 'filament.pages.store-settings';

    // ── Hours ────────────────────────────────────────────────────────────

    /** @var array<string, array<int, array{enabled: bool, open: string, close: string}>> */
    public array $schedules = [];

    /** @var array<string, string> how long the next override lasts, per scope */
    public array $durations = ['store' => 'schedule', 'delivery' => 'schedule'];

    // ── Store settings state ─────────────────────────────────────────────

    public int    $auto_confirm_minutes    = 30;
    public string $closed_message          = '';
    public string $delivery_closed_message = '';

    // ── Profile state ────────────────────────────────────────────────────

    public string $profile_name     = '';
    public string $profile_email    = '';
    public string $current_password = '';
    public string $new_password     = '';
    public string $new_password_confirmation = '';

    public function mount(): void
    {
        $hours = app(StoreHours::class);

        $this->schedules = [
            'store'    => $hours->schedule('store'),
            'delivery' => $hours->schedule('delivery'),
        ];

        $this->auto_confirm_minutes    = StoreSetting::getInt('auto_confirm_minutes', 30);
        $this->closed_message          = StoreSetting::closedMessage();
        $this->delivery_closed_message = StoreSetting::deliveryClosedMessage();

        $user = auth()->user();
        $this->profile_name  = $user->name;
        $this->profile_email = $user->email;
    }

    // ── Read helpers for the view ────────────────────────────────────────

    public function canManage(): bool
    {
        return auth()->user()?->isManager() ?? false;
    }

    /**
     * The live state of one scope, with the sentences shown on the card.
     *
     * @return array<string, mixed>
     */
    public function statusFor(string $scope): array
    {
        $status = app(StoreHours::class)->status($scope);

        $label = match (true) {
            $status['source'] === 'store_closed' => 'مغلق لأن المتجر مغلق',
            $status['source'] === 'override'     => ($status['open'] ? 'مفتوح استثنائياً' : 'مغلق استثنائياً')
                . ($status['override']['until']
                    ? ' حتى ' . $this->moment($status['override']['until'])
                    : ' حتى إعادته للجدول يدوياً'),
            default                              => $status['open'] ? 'مفتوح حسب الجدول' : 'مغلق حسب الجدول',
        };

        $next = match (true) {
            $status['open'] && $status['closesAt'] !== null   => 'يُغلق ' . $this->moment($status['closesAt']),
            $status['open']                                  => 'مفتوح دون إغلاق مجدول هذا الأسبوع',
            $status['opensAt'] !== null                      => 'يفتح ' . $this->moment($status['opensAt']),
            default                                          => 'لا يوجد فتح مجدول هذا الأسبوع',
        };

        return $status + ['label' => $label, 'next' => $next];
    }

    public function nowLabel(): string
    {
        return $this->moment(app(StoreHours::class)->now()->toIso8601String()) . ' (' . app(StoreHours::class)->timezone() . ')';
    }

    // ── Hours actions (managers only) ────────────────────────────────────

    public function saveSchedule(string $scope): void
    {
        if (! $this->authorizeManager() || ! $this->validScope($scope)) {
            return;
        }

        try {
            app(StoreHours::class)->saveSchedule($scope, $this->schedules[$scope] ?? []);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->schedules[$scope] = app(StoreHours::class)->schedule($scope);

        Notification::make()
            ->title($scope === 'store' ? '✅ تم حفظ مواعيد عمل المتجر' : '✅ تم حفظ مواعيد التوصيل')
            ->success()
            ->send();
    }

    public function forceOpen(string $scope): void
    {
        $this->force($scope, 'open');
    }

    public function forceClose(string $scope): void
    {
        $this->force($scope, 'closed');
    }

    public function resumeSchedule(string $scope): void
    {
        if (! $this->authorizeManager() || ! $this->validScope($scope)) {
            return;
        }

        app(StoreHours::class)->clearOverride($scope);

        Notification::make()->title('تمت العودة إلى مواعيد الجدول')->success()->send();
    }

    public function saveStoreSettings(): void
    {
        if (! $this->authorizeManager()) {
            return;
        }

        StoreSetting::set('auto_confirm_minutes',    (string) max(5, min(240, $this->auto_confirm_minutes)));
        StoreSetting::set('closed_message',          trim($this->closed_message));
        StoreSetting::set('delivery_closed_message', trim($this->delivery_closed_message));

        Notification::make()->title('✅ تم حفظ إعدادات المتجر')->success()->send();
    }

    // ── Profile ──────────────────────────────────────────────────────────

    public function saveProfile(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $user->update([
            'name'  => $this->profile_name,
            'email' => $this->profile_email,
        ]);

        Notification::make()->title('✅ تم تحديث الملف الشخصي')->success()->send();
    }

    public function changePassword(): void
    {
        /** @var User $user */
        $user = auth()->user();

        if (! Hash::check($this->current_password, $user->password)) {
            Notification::make()->title('❌ كلمة السر الحالية غير صحيحة')->danger()->send();
            return;
        }

        if (strlen($this->new_password) < 6) {
            Notification::make()->title('❌ كلمة السر الجديدة قصيرة جداً (6 أحرف على الأقل)')->danger()->send();
            return;
        }

        if ($this->new_password !== $this->new_password_confirmation) {
            Notification::make()->title('❌ كلمة السر الجديدة غير متطابقة')->danger()->send();
            return;
        }

        $user->update(['password' => Hash::make($this->new_password)]);

        $this->current_password = '';
        $this->new_password = '';
        $this->new_password_confirmation = '';

        Notification::make()->title('✅ تم تغيير كلمة السر')->success()->send();
    }

    public static function canAccess(): bool
    {
        return true;
    }

    // ── internals ────────────────────────────────────────────────────────

    private function force(string $scope, string $state): void
    {
        if (! $this->authorizeManager() || ! $this->validScope($scope)) {
            return;
        }

        $hours    = app(StoreHours::class);
        $duration = array_key_exists($this->durations[$scope] ?? '', StoreHours::DURATIONS)
            ? $this->durations[$scope]
            : 'schedule';

        $until = $hours->overrideUntil($scope, $state, $duration);
        $hours->setOverride($scope, $state, $until);

        $what = $scope === 'store' ? 'المتجر' : 'التوصيل';

        Notification::make()
            ->title(($state === 'open' ? "تم فتح {$what} استثنائياً" : "تم إغلاق {$what} استثنائياً")
                . ($until ? ' حتى ' . $this->moment($until->toIso8601String()) : ' حتى إعادته للجدول'))
            ->success()
            ->send();
    }

    private function authorizeManager(): bool
    {
        if ($this->canManage()) {
            return true;
        }

        Notification::make()->title('هذا الإجراء متاح للمدير فقط')->danger()->send();

        return false;
    }

    private function validScope(string $scope): bool
    {
        return in_array($scope, StoreHours::SCOPES, true);
    }

    /** "اليوم 23:00", "غداً 10:00", "السبت 14:00", or a date further out. */
    private function moment(?string $iso): string
    {
        if ($iso === null) {
            return '—';
        }

        $hours = app(StoreHours::class);
        $at    = CarbonImmutable::parse($iso)->setTimezone($hours->timezone());
        $today = $hours->now()->startOfDay();
        $days  = (int) $today->diffInDays($at->startOfDay(), false);

        return match (true) {
            $days === 0            => 'اليوم ' . $at->format('H:i'),
            $days === 1            => 'غداً ' . $at->format('H:i'),
            $days > 1 && $days < 7 => StoreHours::DAYS[$at->dayOfWeek][1] . ' ' . $at->format('H:i'),
            default                => $at->format('d/m H:i'),
        };
    }
}
