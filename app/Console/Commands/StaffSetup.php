<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * The two dashboard accounts the shop runs on.
 *
 * One command rather than two `admin:user create` calls, because the pair is
 * the point: the manager's login is the one that can change what the shop
 * charges and where its money goes, and it must not be the login left signed in
 * on the counter screen all day.
 *
 * It also checks for the account the old seeder used to create — admin@glace.com
 * with the password "admin123456", a full manager — and says so loudly, because
 * on any install that ran `db:seed` to get its menu, that login is live.
 */
class StaffSetup extends Command
{
    protected $signature = 'staff:setup
        {--manager-email=admin@glaceelameer.com : The manager account}
        {--counter-email=cashier@glaceelameer.com : The counter account}
        {--manager-password= : Generated when omitted}
        {--counter-password= : Generated when omitted}
        {--rotate : Give a new password to an account that already exists}';

    protected $description = 'إنشاء حسابي المدير والكاشير وطباعة بياناتهما مرة واحدة';

    /** Passwords a person has to read off a screen and type — no 0/O, no 1/l. */
    private const ALPHABET = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** What the old seeder used to put on every install. */
    private const SEEDED_PASSWORD = 'admin123456';

    public function handle(): int
    {
        $rows = [
            $this->account(User::ROLE_MANAGER, 'مدير النظام', (string) $this->option('manager-email'), $this->option('manager-password')),
            $this->account(User::ROLE_ACCOUNTANT, 'كاشير', (string) $this->option('counter-email'), $this->option('counter-password')),
        ];

        $this->newLine();
        $this->table(['الدور', 'الاسم', 'الإيميل', 'كلمة السر'], $rows);

        $this->warn('  اكتب كلمات السر الآن — لا تُطبع مرة أخرى. لتغيير واحدة: php artisan user:password <email>');
        $this->newLine();

        $this->reportSeededLogins();

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function account(string $role, string $name, string $email, ?string $password): array
    {
        $user     = User::where('email', $email)->first();
        $label    = User::ROLES[$role];
        $generate = fn () => $password ?: $this->generate();

        if (! $user) {
            User::create([
                'name'     => $name,
                'email'    => $email,
                'role'     => $role,
                'password' => Hash::make($fresh = $generate()),
            ]);

            return [$label, $name, $email, $fresh];
        }

        // An account that exists keeps its password unless asked, so running
        // this twice does not sign somebody out mid-shift. The role is put
        // right either way — that is the part that must not drift.
        if ($user->role !== $role) {
            $user->update(['role' => $role]);
        }

        if (! $this->option('rotate') && ! $password) {
            return [$label, $user->name, $email, '— كما هي —'];
        }

        $user->update(['password' => Hash::make($fresh = $generate())]);

        return [$label, $user->name, $email, $fresh];
    }

    /**
     * Any account still on the password the seeder used to set.
     *
     * Checked by trying it rather than by email, because the row may have been
     * renamed: what matters is whether the password still opens the door.
     */
    private function reportSeededLogins(): void
    {
        $exposed = User::all()->filter(fn (User $user) => Hash::check(self::SEEDED_PASSWORD, $user->password));

        if ($exposed->isEmpty()) {
            return;
        }

        $this->error('  ⚠  حسابات ما زالت على كلمة السر الافتراضية القديمة — وهي معروفة لأي أحد:');

        foreach ($exposed as $user) {
            $this->error("     {$user->email}  ({$user->role})");
        }

        $this->newLine();
        $this->warn('  غيّرها الآن، أو احذفها إن لم تعد مستخدمة:');

        foreach ($exposed as $user) {
            $this->line("     php artisan user:password {$user->email}");
        }

        $this->newLine();
    }

    private function generate(): string
    {
        $alphabet = self::ALPHABET;

        return collect(range(1, 16))
            ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
            ->implode('');
    }
}
