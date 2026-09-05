<?php

namespace App\Services\Printing;

use App\Support\ArabicShaper;
use RuntimeException;

/**
 * Talks ESC/POS to a network thermal printer over a raw TCP socket.
 *
 * Receipt printers listen on port 9100 and speak a byte protocol, not HTTP:
 * you send text with control sequences interleaved and it prints as it reads.
 *
 * Arabic needs care. Two code pages are in play and they want opposite things:
 *
 *   CP864  — carries the Arabic *presentation forms*, so the text must be
 *            shaped and visually reordered before sending (ArabicShaper).
 *   CP1256 — carries the *base* letters and the printer shapes them itself,
 *            so shaping here would produce mojibake by doing it twice.
 *
 * Which one a given printer wants is a property of the hardware, so it is
 * configuration rather than a decision this class can make.
 */
class EscPosPrinter
{
    // ─── ESC/POS control sequences ──────────────────────────────────────────

    private const ESC = "\x1B";
    private const GS  = "\x1D";

    private const INIT           = self::ESC . '@';
    private const ALIGN_LEFT     = self::ESC . 'a0';
    private const ALIGN_CENTER   = self::ESC . 'a1';
    private const ALIGN_RIGHT    = self::ESC . 'a2';
    private const BOLD_ON        = self::ESC . 'E1';
    private const BOLD_OFF       = self::ESC . 'E0';
    private const DOUBLE_ON      = self::GS . '!' . "\x11";
    private const DOUBLE_OFF     = self::GS . '!' . "\x00";
    private const CUT            = self::GS . 'V' . "\x41" . "\x03";
    private const DRAWER_KICK    = self::ESC . 'p' . "\x00" . "\x19" . "\xFA";

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config = []) {}

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false) && $this->host() !== '';
    }

    /**
     * Send one already-composed receipt.
     *
     * @throws RuntimeException when the printer cannot be reached
     */
    public function print(ReceiptDocument $document): void
    {
        if (! $this->enabled()) {
            throw new RuntimeException('طابعة الشبكة غير مفعّلة');
        }

        $this->send($this->render($document));
    }

    /** Build the byte stream for a receipt, without sending it. */
    public function render(ReceiptDocument $document): string
    {
        $out = self::INIT . $this->selectCodePage();

        foreach ($document->lines($this->width()) as $line) {
            $out .= match ($line['align'] ?? 'right') {
                'center' => self::ALIGN_CENTER,
                'left'   => self::ALIGN_LEFT,
                default  => self::ALIGN_RIGHT,
            };

            if ($line['bold'] ?? false) {
                $out .= self::BOLD_ON;
            }

            if ($line['large'] ?? false) {
                $out .= self::DOUBLE_ON;
            }

            $out .= $this->encode((string) $line['text']) . "\n";

            if ($line['large'] ?? false) {
                $out .= self::DOUBLE_OFF;
            }

            if ($line['bold'] ?? false) {
                $out .= self::BOLD_OFF;
            }
        }

        // Feed past the tear bar before cutting, or the last lines are still
        // inside the mechanism when the blade comes down.
        $out .= "\n\n\n";

        if ($this->config['open_drawer'] ?? false) {
            $out .= self::DRAWER_KICK;
        }

        if ($this->config['cut'] ?? true) {
            $out .= self::CUT;
        }

        return $out;
    }

    /**
     * Shape (when the code page needs it) and transcode to the printer's page.
     *
     * `//TRANSLIT` rather than a hard failure: one character the page cannot
     * represent must not cost the shop the whole receipt.
     */
    public function encode(string $text): string
    {
        $codepage = strtoupper((string) ($this->config['codepage'] ?? 'CP864'));

        if ($codepage === 'CP864') {
            $text = ArabicShaper::forPrinter($text);
        }

        $encoded = @iconv('UTF-8', $codepage . '//TRANSLIT', $text);

        return $encoded === false ? $text : $encoded;
    }

    /**
     * @throws RuntimeException
     */
    private function send(string $payload): void
    {
        $errno  = 0;
        $errstr = '';

        $socket = @fsockopen(
            $this->host(),
            (int) ($this->config['port'] ?? 9100),
            $errno,
            $errstr,
            (float) ($this->config['timeout'] ?? 5),
        );

        if ($socket === false) {
            throw new RuntimeException("تعذّر الاتصال بالطابعة ({$errstr})");
        }

        try {
            stream_set_timeout($socket, (int) ($this->config['timeout'] ?? 5));

            // fwrite can write short; a truncated receipt is worse than none.
            $written = 0;
            $length  = strlen($payload);

            while ($written < $length) {
                $chunk = @fwrite($socket, substr($payload, $written));

                if ($chunk === false || $chunk === 0) {
                    throw new RuntimeException('انقطع الاتصال بالطابعة أثناء الإرسال');
                }

                $written += $chunk;
            }
        } finally {
            fclose($socket);
        }
    }

    /** ESC t n — selects the character table the following bytes are read in. */
    private function selectCodePage(): string
    {
        $table = match (strtoupper((string) ($this->config['codepage'] ?? 'CP864'))) {
            'CP864'  => 37,   // Arabic, presentation forms
            'CP1256' => 50,   // Arabic, base letters (printer shapes)
            default  => 0,
        };

        return self::ESC . 't' . chr($table);
    }

    private function host(): string
    {
        return trim((string) ($this->config['host'] ?? ''));
    }

    /** Characters per line: 48 on an 80 mm head, 32 on a 58 mm one. */
    private function width(): int
    {
        return (int) ($this->config['width'] ?? 48);
    }
}
