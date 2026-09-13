<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Create or reset an admin user from the command line.
 *
 * Usage:
 *   php artisan admin:reset-password admin@glace.com
 *   php artisan admin:reset-password admin@glace.com --password=newpass123
 *   php artisan admin:create "المدير" admin@glace.com secret123 --role=manager
 */
class AdminUserCommand extends Command
{
    protected $signature = 'admin:user
        {action : create|reset-password|list}
        {email? : Email address}
        {--name= : Name (for create)}
        {--password= : Password (auto-generated if omitted)}
        {--role=manager : Role: manager or accountant}';

    protected $description = 'إدارة مستخدمي لوحة التحكم — إنشاء أو إعادة تعيين كلمة السر';

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'list'           => $this->listUsers(),
            'create'         => $this->createUser(),
            'reset-password' => $this->resetPassword(),
            default          => $this->error('الأوامر المتاحة: list, create, reset-password') ?? 1,
        };
    }

    private function listUsers(): int
    {
        $users = User::all(['id', 'name', 'email', 'role']);
        $this->table(['ID', 'الاسم', 'الإيميل', 'الدور'], $users->toArray());
        return 0;
    }

    private function createUser(): int
    {
        $email    = $this->argument('email');
        $name     = $this->option('name') ?? $this->ask('الاسم');
        $password = $this->option('password') ?? $this->generatePassword();
        $role     = $this->option('role');

        if (! $email || ! $name) {
            $this->error('الإيميل والاسم مطلوبان');
            return 1;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("المستخدم {$email} موجود بالفعل");
            return 1;
        }

        User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => Hash::make($password),
            'role'     => $role,
        ]);

        $this->info("✅ تم إنشاء المستخدم: {$email}");
        $this->info("   كلمة السر: {$password}");
        $this->warn('   احفظ كلمة السر — لن تظهر مرة أخرى!');

        return 0;
    }

    private function resetPassword(): int
    {
        $email = $this->argument('email');

        if (! $email) {
            $this->error('الإيميل مطلوب');
            return 1;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("المستخدم {$email} غير موجود");
            return 1;
        }

        $password = $this->option('password') ?? $this->generatePassword();
        $user->update(['password' => Hash::make($password)]);

        $this->info("✅ تم تغيير كلمة السر لـ {$email}");
        $this->info("   كلمة السر الجديدة: {$password}");
        $this->warn('   احفظ كلمة السر — لن تظهر مرة أخرى!');

        return 0;
    }

    private function generatePassword(): string
    {
        return substr(str_shuffle('abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 12);
    }
}
