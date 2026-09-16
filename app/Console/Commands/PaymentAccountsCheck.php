<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\PaymentAccount;
use Illuminate\Console\Command;

/**
 * What the storefront receives for each payment account, and why.
 *
 * Built for one question that cannot be answered from outside the server:
 * "the API returns no QR code — was it never uploaded, did the upload fail, or
 * was the file lost?" Each answer needs a different fix.
 */
class PaymentAccountsCheck extends Command
{
    protected $signature = 'payment-accounts:check';

    protected $description = 'Report QR images, account numbers and upload limits for payment accounts';

    public function handle(): int
    {
        $accounts = PaymentAccount::orderBy('sort_order')->get();
        $problems = 0;

        $this->newLine();
        $this->line('  PHP upload_max_filesize : ' . ini_get('upload_max_filesize'));
        $this->line('  PHP post_max_size       : ' . ini_get('post_max_size'));

        if ($this->bytes((string) ini_get('upload_max_filesize')) < 4 * 1024 * 1024) {
            $problems++;
            $this->newLine();
            $this->components->error('Uploads above ' . ini_get('upload_max_filesize') . ' are rejected by PHP, but the dashboard allows 4 MB.');
            $this->line('  A larger QR image fails to upload and the field stays empty after saving.');
            $this->line('  Rebuild the image with docker/php-uploads.ini, or raise upload_max_filesize to 10M.');
        }

        if ($accounts->isEmpty()) {
            $this->newLine();
            $this->components->warn('No payment accounts exist yet.');

            return self::FAILURE;
        }

        $rows = [];

        foreach ($accounts as $account) {
            $transfer = $account->isTransferDestination();
            $qr       = $account->qrImageStatus();

            $rows[] = [
                $account->method,
                $account->active ? 'yes' : 'no',
                match ($qr) {
                    'ok'           => 'ok',
                    'missing-file' => 'FILE MISSING',
                    default        => $transfer ? 'NOT UPLOADED' : '—',
                },
                (string) ($account->qr_image ?: '—'),
                $transfer ? ($account->account_number ?: 'EMPTY') : '—',
                $account->qrImageUrl() ?? '(not sent)',
            ];

            if ($account->active && $transfer && $qr !== 'ok') {
                $problems++;
            }
        }

        $this->newLine();
        $this->table(['method', 'active', 'qr', 'stored path', 'account no.', 'qrImage sent to the storefront'], $rows);

        $this->newLine();
        $this->line('  NOT UPLOADED  → upload the QR from the dashboard, wait for the upload to finish, then save.');
        $this->line('  FILE MISSING  → the row points at a file that is gone; upload it again.');
        $this->line('  account no. EMPTY → fill «رقم الحساب» in the dashboard; empty fields are not sent.');

        if ($problems > 0) {
            $this->newLine();
            $this->components->error("{$problems} problem(s) found.");

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Every active transfer account has a working QR image.');

        return self::SUCCESS;
    }

    private function bytes(string $value): int
    {
        $value = trim($value);
        $unit  = strtolower(substr($value, -1));
        $size  = (int) $value;

        return match ($unit) {
            'g'     => $size * 1024 * 1024 * 1024,
            'm'     => $size * 1024 * 1024,
            'k'     => $size * 1024,
            default => $size,
        };
    }
}
