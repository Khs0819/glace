<?php

namespace App\Console\Commands;

use App\Services\Printing\EscPosPrinter;
use App\Support\ArabicShaper;
use Illuminate\Console\Command;
use Throwable;

/**
 * Print one slip that tries every plausible Arabic code page in turn.
 *
 * `ESC t n` selects the table the printer decodes our bytes with, and `n` is
 * not standardised: CP864 is 37 on an Epson and something else on a Bixolon,
 * so a number taken from the wrong datasheet selects an unrelated table and
 * Arabic comes out as Latin punctuation. That looks identical to a shaping bug
 * and is not one — which is why guessing at it wastes an afternoon.
 *
 * This sidesteps the datasheets entirely. Each block prints the SAME Arabic
 * word under a different index, twice: once shaped by us (what CP864 wants)
 * and once raw (what CP1256 wants). Whoever is standing at the printer reads
 * the paper, finds the line that is legible, and that block's two numbers are
 * the settings. One minute, no ambiguity.
 */
class PrinterCodepages extends Command
{
    protected $signature = 'printer:codepages
        {--tables= : Comma-separated indices to try (default: the usual Arabic ones)}
        {--text=مرحبا بكم في جلاسيه الأمير : The Arabic sample to print}';

    protected $description = 'Print a calibration slip to find the printer\'s Arabic code page index';

    /**
     * Indices that carry Arabic on the common makes.
     *
     * Deliberately a wide net: the cost of an extra block is three lines of
     * paper, and the cost of a missing one is another trip to the shop.
     */
    private const CANDIDATES = [
        22,  // CP864 on several Bixolon / Citizen models
        37,  // CP864 on Epson
        50,  // CP1256 on Epson
        17,  // CP864 on some Star / Rongta
        21,  // CP1256 on several Bixolon models
        24,  // CP864 variant
        32,  // CP1256 variant
        33,  // Arabic on some clones
    ];

    public function handle(EscPosPrinter $printer): int
    {
        if (! $printer->enabled()) {
            $this->error('طابعة الشبكة غير مفعّلة — اضبط GLACE_PRINTER_ENABLED و GLACE_PRINTER_HOST.');

            return self::FAILURE;
        }

        $tables = $this->tables();
        $sample = (string) $this->option('text');

        $this->info('Printing a calibration slip with ' . count($tables) . ' code page(s)…');

        try {
            $printer->sendRaw($this->slip($printer, $tables, $sample));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('  اقرأ الورقة، وابحث عن السطر الذي تظهر فيه العربية سليمة ومتصلة.');
        $this->newLine();
        $this->line('  إن كان السطر تحت «shaped»  →  GLACE_PRINTER_CODEPAGE=CP864');
        $this->line('  إن كان السطر تحت «raw»     →  GLACE_PRINTER_CODEPAGE=CP1256');
        $this->line('  وفي الحالتين ضع رقم الكتلة في  GLACE_PRINTER_CODEPAGE_TABLE');
        $this->newLine();
        $this->comment('  ثم: php artisan config:clear && php artisan printer:check --test');

        return self::SUCCESS;
    }

    /** @param array<int, int> $tables */
    private function slip(EscPosPrinter $printer, array $tables, string $sample): string
    {
        $esc = "\x1B";
        $gs  = "\x1D";

        $out = $esc . '@' . $esc . 'a1'        // init, centre
            . $esc . 'E1' . "معايرة صفحة الترميز\n" . $esc . 'E0'
            . str_repeat('-', 42) . "\n"
            . $esc . 'a2';                      // right-align for the blocks

        foreach ($tables as $table) {
            $out .= $printer->codePageCommand(0)      // ASCII for the heading
                . "\n[ table {$table} ]\n"
                . $printer->codePageCommand($table);

            // Shaped: presentation forms, reordered. What CP864 expects.
            $out .= '  shaped: ' . $this->encode(ArabicShaper::forPrinter($sample)) . "\n";

            // Raw: base letters, printer shapes them. What CP1256 expects.
            $out .= '  raw   : ' . $this->encode($sample) . "\n";
        }

        return $out
            . $printer->codePageCommand(0)
            . "\n" . str_repeat('-', 42) . "\n\n\n"
            . $gs . 'V' . "\x41" . "\x03";       // partial cut
    }

    /**
     * Bytes as the printer will read them.
     *
     * iconv is asked for CP1256 because it is the one encoding that survives
     * every Arabic letter; the printer's own table decides how to draw them,
     * which is the whole point of the experiment.
     */
    private function encode(string $text): string
    {
        $encoded = @iconv('UTF-8', 'CP1256//TRANSLIT', $text);

        return $encoded === false ? $text : $encoded;
    }

    /** @return array<int, int> */
    private function tables(): array
    {
        $option = trim((string) $this->option('tables'));

        if ($option === '') {
            return self::CANDIDATES;
        }

        return array_values(array_filter(
            array_map('intval', explode(',', $option)),
            static fn (int $n) => $n >= 0 && $n <= 255,
        ));
    }
}
