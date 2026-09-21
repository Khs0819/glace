<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Set a dashboard password from the server, for when it has been forgotten.
 *
 * The dashboard's own "forgot password" link would need working email, and
 * this server does not send any. Anyone with shell access to the server is
 * already trusted with the database, so this is the recovery path: it needs
 * nothing but the email the user signs in with.
 */
class UserPassword extends Command
{
    protected $signature = 'user:password
        {email? : The email the user signs in with (omit to list the users)}
        {--password= : The new password (omit to be asked, or to generate one)}
        {--manager : Also make this user a manager}';

    protected $description = 'Reset a dashboard user password, or list who can sign in';

    public function handle(): int
    {
        $email = $this->argument('email');

        if (! $email) {
            $this->table(
                ['البريد (اسم الدخول)', 'الاسم', 'الصلاحية'],
                User::orderBy('email')->get()->map(fn (User $user) => [
                    $user->email,
                    $user->name,
                    User::ROLES[$user->role] ?? $user->role,
                ])->all(),
            );

            $this->line('  لإعادة التعيين: php artisan user:password <البريد>');

            return self::SUCCESS;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->components->error("لا يوجد مستخدم بالبريد {$email}. شغّل الأمر بدون بريد لرؤية المستخدمين.");

            return self::FAILURE;
        }

        $password = $this->option('password');
        $generated = false;

        if (! $password && $this->input->isInteractive()) {
            $password = $this->secret('كلمة السر الجديدة (اتركها فارغة لتوليد واحدة)');
        }

        if (! $password) {
            $password  = Str::password(12, symbols: false);
            $generated = true;
        }

        if (mb_strlen($password) < 8) {
            $this->components->error('كلمة السر يجب أن تكون 8 أحرف على الأقل.');

            return self::FAILURE;
        }

        // Hashed by the model's cast; never stored as typed.
        $user->forceFill(array_filter([
            'password'       => $password,
            'role'           => $this->option('manager') ? User::ROLE_MANAGER : null,
            'remember_token' => Str::random(60),
        ]))->save();

        $this->components->info("تم تعيين كلمة سر جديدة لـ {$user->name} ({$user->email}).");

        if ($generated) {
            $this->line("  كلمة السر: {$password}");
            $this->line('  غيّرها بعد الدخول من «إعدادات المتجر ← تغيير كلمة السر».');
        }

        return self::SUCCESS;
    }
}
