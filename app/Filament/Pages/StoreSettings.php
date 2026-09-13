<?php

namespace App\Filament\Pages;

use App\Models\StoreSetting;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;

/**
 * Store settings page — controls store open/close, delivery toggle,
 * auto-confirm timer, and admin user management.
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

    // ── Store settings state ─────────────────────────────────────────────

    public bool   $store_open           = true;
    public bool   $delivery_open        = true;
    public int    $auto_confirm_minutes = 30;
    public string $closed_message       = '';

    // ── Profile state ────────────────────────────────────────────────────

    public string $profile_name     = '';
    public string $profile_email    = '';
    public string $current_password = '';
    public string $new_password     = '';
    public string $new_password_confirmation = '';

    public function mount(): void
    {
        $this->store_open           = StoreSetting::getBool('store_open');
        $this->delivery_open        = StoreSetting::getBool('delivery_open');
        $this->auto_confirm_minutes = StoreSetting::getInt('auto_confirm_minutes', 30);
        $this->closed_message       = StoreSetting::get('closed_message', '');

        $user = auth()->user();
        $this->profile_name  = $user->name;
        $this->profile_email = $user->email;
    }

    // ── Actions ──────────────────────────────────────────────────────────

    public function saveStoreSettings(): void
    {
        StoreSetting::set('store_open',           $this->store_open ? '1' : '0');
        StoreSetting::set('delivery_open',        $this->delivery_open ? '1' : '0');
        StoreSetting::set('auto_confirm_minutes', (string) $this->auto_confirm_minutes);
        StoreSetting::set('closed_message',       $this->closed_message);

        Notification::make()->title('✅ تم حفظ إعدادات المتجر')->success()->send();
    }

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
}
