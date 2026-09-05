<?php

namespace App\Support;

/**
 * Turns logical Arabic text into the shaped, visually-ordered form a thermal
 * printer needs.
 *
 * A browser does this for us. A receipt printer does not: it draws code points
 * left to right, one glyph each, with no notion of joining or direction. Send
 * it "مرحبا" unchanged and it prints five disconnected, backwards letters.
 *
 * Two transformations, in this order:
 *
 *  1. SHAPING — each letter takes one of four contextual forms (isolated,
 *     initial, medial, final) from the Unicode Arabic Presentation Forms-B
 *     block, decided by whether its neighbours join. Lam followed by Alef is a
 *     mandatory ligature and becomes a single glyph.
 *
 *  2. REORDERING — the shaped run is reversed so that printing it left to right
 *     produces right-to-left text. Latin and digit runs inside it are reversed
 *     back, because those read left to right even inside an Arabic sentence.
 *
 * Only needed for code pages that carry presentation forms (CP864). A printer
 * set to CP1256 gets the base letters and shapes them itself, so shaping here
 * would double up — EscPosPrinter decides which applies.
 */
class ArabicShaper
{
    /**
     * letter => [isolated, final, initial, medial]
     *
     * A two-entry row is a letter that never joins to its left, so it has no
     * initial or medial form — that is what makes "دد" two separate glyphs.
     *
     * @var array<string, array<int, string>>
     */
    private const FORMS = [
        'ء' => ["\u{FE80}"],
        'آ' => ["\u{FE81}", "\u{FE82}"],
        'أ' => ["\u{FE83}", "\u{FE84}"],
        'ؤ' => ["\u{FE85}", "\u{FE86}"],
        'إ' => ["\u{FE87}", "\u{FE88}"],
        'ئ' => ["\u{FE89}", "\u{FE8A}", "\u{FE8B}", "\u{FE8C}"],
        'ا' => ["\u{FE8D}", "\u{FE8E}"],
        'ب' => ["\u{FE8F}", "\u{FE90}", "\u{FE91}", "\u{FE92}"],
        'ة' => ["\u{FE93}", "\u{FE94}"],
        'ت' => ["\u{FE95}", "\u{FE96}", "\u{FE97}", "\u{FE98}"],
        'ث' => ["\u{FE99}", "\u{FE9A}", "\u{FE9B}", "\u{FE9C}"],
        'ج' => ["\u{FE9D}", "\u{FE9E}", "\u{FE9F}", "\u{FEA0}"],
        'ح' => ["\u{FEA1}", "\u{FEA2}", "\u{FEA3}", "\u{FEA4}"],
        'خ' => ["\u{FEA5}", "\u{FEA6}", "\u{FEA7}", "\u{FEA8}"],
        'د' => ["\u{FEA9}", "\u{FEAA}"],
        'ذ' => ["\u{FEAB}", "\u{FEAC}"],
        'ر' => ["\u{FEAD}", "\u{FEAE}"],
        'ز' => ["\u{FEAF}", "\u{FEB0}"],
        'س' => ["\u{FEB1}", "\u{FEB2}", "\u{FEB3}", "\u{FEB4}"],
        'ش' => ["\u{FEB5}", "\u{FEB6}", "\u{FEB7}", "\u{FEB8}"],
        'ص' => ["\u{FEB9}", "\u{FEBA}", "\u{FEBB}", "\u{FEBC}"],
        'ض' => ["\u{FEBD}", "\u{FEBE}", "\u{FEBF}", "\u{FEC0}"],
        'ط' => ["\u{FEC1}", "\u{FEC2}", "\u{FEC3}", "\u{FEC4}"],
        'ظ' => ["\u{FEC5}", "\u{FEC6}", "\u{FEC7}", "\u{FEC8}"],
        'ع' => ["\u{FEC9}", "\u{FECA}", "\u{FECB}", "\u{FECC}"],
        'غ' => ["\u{FECD}", "\u{FECE}", "\u{FECF}", "\u{FED0}"],
        'ف' => ["\u{FED1}", "\u{FED2}", "\u{FED3}", "\u{FED4}"],
        'ق' => ["\u{FED5}", "\u{FED6}", "\u{FED7}", "\u{FED8}"],
        'ك' => ["\u{FED9}", "\u{FEDA}", "\u{FEDB}", "\u{FEDC}"],
        'ل' => ["\u{FEDD}", "\u{FEDE}", "\u{FEDF}", "\u{FEE0}"],
        'م' => ["\u{FEE1}", "\u{FEE2}", "\u{FEE3}", "\u{FEE4}"],
        'ن' => ["\u{FEE5}", "\u{FEE6}", "\u{FEE7}", "\u{FEE8}"],
        'ه' => ["\u{FEE9}", "\u{FEEA}", "\u{FEEB}", "\u{FEEC}"],
        'و' => ["\u{FEED}", "\u{FEEE}"],
        'ى' => ["\u{FEEF}", "\u{FEF0}"],
        'ي' => ["\u{FEF1}", "\u{FEF2}", "\u{FEF3}", "\u{FEF4}"],
    ];

    /** Lam + Alef is not two glyphs; it is one. alef => [isolated, final] */
    private const LAM_ALEF = [
        'آ' => ["\u{FEF5}", "\u{FEF6}"],
        'أ' => ["\u{FEF7}", "\u{FEF8}"],
        'إ' => ["\u{FEF9}", "\u{FEFA}"],
        'ا' => ["\u{FEFB}", "\u{FEFC}"],
    ];

    /** Combining marks: invisible for joining purposes, so they are dropped. */
    private const TASHKEEL = [
        "\u{064B}", "\u{064C}", "\u{064D}", "\u{064E}", "\u{064F}", "\u{0650}",
        "\u{0651}", "\u{0652}", "\u{0653}", "\u{0654}", "\u{0655}", "\u{0640}",
    ];

    /** Shaped and visually ordered — ready to hand to a printer. */
    public static function forPrinter(string $text): string
    {
        return self::reorder(self::shape($text));
    }

    /**
     * Apply contextual forms, leaving the string in logical order.
     */
    public static function shape(string $text): string
    {
        $text = str_replace(self::TASHKEEL, '', $text);

        /** @var array<int, string> $chars */
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out   = [];
        $count = count($chars);

        for ($i = 0; $i < $count; $i++) {
            $char = $chars[$i];

            if (! isset(self::FORMS[$char])) {
                $out[] = $char;

                continue;
            }

            $next = $chars[$i + 1] ?? null;

            // Lam + Alef consumes both characters and emits one glyph, so the
            // loop skips ahead rather than shaping the Alef separately.
            if ($char === 'ل' && $next !== null && isset(self::LAM_ALEF[$next])) {
                $joinsBefore = self::joinsForward($chars, $i - 1);

                $out[] = self::LAM_ALEF[$next][$joinsBefore ? 1 : 0];
                $i++;

                continue;
            }

            $forms = self::FORMS[$char];

            // "Joins before" means the previous letter can reach forward to
            // this one; "joins after" means this letter can reach the next.
            $before = self::joinsForward($chars, $i - 1);
            $after  = count($forms) > 2 && self::joinsBackward($chars, $i + 1);

            $out[] = match (true) {
                $before && $after  => $forms[3],  // medial
                $after             => $forms[2],  // initial
                $before            => $forms[1],  // final
                default            => $forms[0],  // isolated
            };
        }

        return implode('', $out);
    }

    /**
     * Reverse the string for a left-to-right printer, keeping runs of Latin
     * letters and digits readable.
     *
     * "الطلب ORD-M3K2A1" must not come out with the reference backwards.
     */
    public static function reorder(string $text): string
    {
        return implode("\n", array_map(
            static fn (string $line) => self::reorderLine($line),
            explode("\n", $text),
        ));
    }

    /**
     * A left-to-right token: letters and digits, plus the punctuation that
     * lives *inside* one.
     *
     * The inner punctuation matters. Scanning character by character would end
     * the run at the hyphen in "ORD-M3K2A1" and reverse the two halves
     * independently, printing "M3K2A1-ORD". Same for "14:32" and "12.50".
     */
    private const LTR_TOKEN = '/[A-Za-z0-9]+(?:[-._:\/+@][A-Za-z0-9]+)*/u';

    private static function reorderLine(string $line): string
    {
        // Split into whole LTR tokens and single other characters, so a token
        // survives the reversal in one piece.
        $segments = [];
        $offset   = 0;

        if (preg_match_all(self::LTR_TOKEN, $line, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as [$token, $at]) {
                foreach (self::chars(substr($line, $offset, $at - $offset)) as $char) {
                    $segments[] = [$char, false];
                }

                $segments[] = [$token, true];
                $offset     = $at + strlen($token);
            }
        }

        foreach (self::chars(substr($line, $offset)) as $char) {
            $segments[] = [$char, false];
        }

        $out = '';

        foreach (array_reverse($segments) as [$text, $isToken]) {
            $out .= $isToken ? $text : self::mirror($text);
        }

        return $out;
    }

    /** @return array<int, string> */
    private static function chars(string $text): array
    {
        return $text === '' ? [] : (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /** Brackets point the other way once the line is reversed. */
    private static function mirror(string $char): string
    {
        return ['(' => ')', ')' => '(', '[' => ']', ']' => '[', '{' => '}', '}' => '{',
                '<' => '>', '>' => '<'][$char] ?? $char;
    }

    /**
     * Can the character at $index reach forward to the next one?
     *
     * True only for letters that have an initial/medial form — a two-form
     * letter such as Dal is a dead end.
     *
     * @param  array<int, string>  $chars
     */
    private static function joinsForward(array $chars, int $index): bool
    {
        $char = $chars[$index] ?? null;

        return $char !== null
            && isset(self::FORMS[$char])
            && count(self::FORMS[$char]) > 2;
    }

    /**
     * Can the character at $index be reached from the previous one?
     *
     * True for any Arabic letter: every one of them has a final form.
     *
     * @param  array<int, string>  $chars
     */
    private static function joinsBackward(array $chars, int $index): bool
    {
        $char = $chars[$index] ?? null;

        return $char !== null && isset(self::FORMS[$char]);
    }
}
