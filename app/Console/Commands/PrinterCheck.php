<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Printing\EscPosPrinter;
use App\Services\Printing\ReceiptDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Whether a receipt will actually come out when an order lands.
 *
 * Three things have to hold, and only one of them is the printer:
 *
 *   1. The printer answers on its IP and port.
 *   2. The queue has a worker. Printing is dispatched as a job, so on a
 *      queue with nothing consuming it the row is written and never runs —
 *      the order succeeds, the customer is told it worked, and no paper ever
 *      comes out. Nothing reports an error, because nothing failed.
 *   3. Arabic decodes to Arabic, which is the code page question.
 *
 * The second is the one that bites, because it is invisible: every test that
 * runs the job synchronously passes, and production quietly prints nothing.
 */
class PrinterCheck extends Command
{
    protected $signature = 'printer:check
        {--test : Print a real test receipt}
        {--order= : Reprint a specific order reference instead of a sample}';

    protected $description = 'Check the printer, the print queue, and the Arabic code page';

    public function handle(EscPosPrinter $printer): int
    {
        $config = (array) config('storefront.printer');

        $this->newLine();
        $this->line('  Enabled  : ' . (($config['enabled'] ?? false) ? 'yes' : 'NO — GLACE_PRINTER_ENABLED=false'));
        $this->line('  Host     : ' . ($config['host'] ?: 'NOT SET') . ':' . ($config['port'] ?? 9100));
        $this->line('  Width    : ' . ($config['width'] ?? 48) . ' chars  (48 = 80 mm, 32 = 58 mm)');
        $this->line('  Codepage : ' . ($config['codepage'] ?? '—')
            . '  table ' . ($config['codepage_table'] ?: 'auto (Epson numbering)'));
        $this->line('  Cut      : ' . (($config['cut'] ?? true) ? 'yes' : 'no'));

        $ok = $this->reportQueue();

        if (! $printer->enabled()) {
            $this->newLine();
            $this->components->error('Network printing is off — only the cashier screen will print.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('  Reaching ' . $config['host'] . ':' . ($config['port'] ?? 9100) . '…');

        if (! $printer->networkAvailable()) {
            $this->components->error('No answer. Check the cable, the IP, and that the printer is on the same network.');

            return self::FAILURE;
        }

        $this->components->info('Printer answered.');

        if (! $this->option('test') && ! $this->option('order')) {
            $this->newLine();
            $this->comment('  Add --test to print a real receipt.');

            return $ok ? self::SUCCESS : self::FAILURE;
        }

        return $this->printSample($printer) ? ($ok ? self::SUCCESS : self::FAILURE) : self::FAILURE;
    }

    /**
     * The silent failure: a queued print job with nobody to run it.
     *
     * @return bool whether automatic printing can actually happen
     */
    private function reportQueue(): bool
    {
        $connection = (string) config('queue.default');

        $this->newLine();
        $this->line('  Queue    : ' . $connection);

        if ($connection === 'sync') {
            // Printing then happens inside the web request: it works, but a
            // dead printer makes the customer wait for the timeout.
            $this->comment('  Receipts print inside the request — works, but a slow printer slows checkout.');

            return true;
        }

        if ($connection !== 'database') {
            $this->comment('  Make sure a worker is consuming this queue, or receipts never print.');

            return true;
        }

        try {
            $pending = DB::table('jobs')->count();
            $failed  = DB::table('failed_jobs')->count();
        } catch (Throwable) {
            $this->comment('  Could not read the jobs table.');

            return true;
        }

        $this->line('  Pending  : ' . $pending . ' job(s)   Failed: ' . $failed);

        if ($pending > 20) {
            // Jobs piling up with none completing is what "no worker" looks
            // like from the outside.
            $this->newLine();
            $this->components->error('Jobs are piling up — no worker appears to be running.');
            $this->line('  Receipts are being queued and never printed. Start one with:');
            $this->line('    php artisan queue:work --queue=default --tries=2');

            return false;
        }

        return true;
    }

    private function printSample(EscPosPrinter $printer): bool
    {
        $order = $this->option('order')
            ? Order::with('items')->where('reference', $this->option('order'))->first()
            : Order::with('items')->latest('id')->first();

        if (! $order) {
            $this->error('No order to print. Place one first, or pass --order=ORD-XXXXXX.');

            return false;
        }

        $this->line('  Printing ' . $order->reference . '…');

        try {
            $printer->print(new ReceiptDocument($order));
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return false;
        }

        $this->components->info('Sent.');
        $this->newLine();
        // The two failures that look alike and are not.
        $this->line('  Read the slip:');
        $this->line('    · Arabic legible and connected  → the code page is right.');
        $this->line('    · Latin punctuation or boxes    → wrong table: php artisan printer:codepages');
        $this->line('    · Letters detached or reversed  → wrong CODEPAGE (CP864 vs CP1256), same command.');

        return true;
    }
}
