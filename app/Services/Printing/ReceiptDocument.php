<?php

namespace App\Services\Printing;

use App\Models\Order;
use App\Models\OrderItem;

/**
 * What goes on a receipt, once, for both renderers.
 *
 * The ESC/POS printer and the browser print view must not drift apart — a
 * reprint from the screen has to match the paper the customer already has. So
 * the content is assembled here and each renderer only decides how to draw it.
 *
 * `lines()` produces the printer's view: a flat list of {text, align, bold},
 * already wrapped to the paper width. The Blade view reads the same accessors
 * and lays them out with CSS instead.
 */
class ReceiptDocument
{
    public function __construct(
        public readonly Order $order,
        /** @var array<string, mixed> */
        private readonly array $shop = [],
    ) {}

    public function shopName(): string
    {
        return (string) ($this->shop['name'] ?? config('app.name'));
    }

    /** @return array<int, string> */
    public function shopLines(): array
    {
        return array_values(array_filter([
            $this->shop['address'] ?? null,
            $this->shop['phone'] ?? null,
            $this->shop['tax_number'] ?? null,
        ]));
    }

    public function footer(): string
    {
        return (string) ($this->shop['footer'] ?? 'شكراً لزيارتكم');
    }

    /**
     * The banner the kitchen and the counter read first.
     *
     * Deliberately the largest thing on the paper: someone glancing at a stack
     * of dockets needs to know at once whether this one goes to a table, a bag
     * or a driver.
     */
    public function kind(): string
    {
        return match ($this->order->delivery_method) {
            'dine-in'  => 'داخل المحل',
            'pickup'   => 'استلام من المحل',
            'delivery' => 'توصيل',
            default    => '',
        };
    }

    /**
     * The heading that identifies where the order goes — a table number for a
     * dine-in order, the area for a delivery.
     */
    public function destination(): ?string
    {
        if ($this->order->isDineIn()) {
            return $this->order->table_number === null
                ? null
                : 'طاولة ' . $this->order->table_number;
        }

        if ($this->order->delivery_method === 'delivery') {
            return $this->order->address['area'] ?? null;
        }

        return null;
    }

    /**
     * The shop and what kind of order this is, on one line.
     *
     * Paper costs money in Gaza, and these were two lines saying one thing
     * each. The name goes light and the kind bold: the kind is what somebody
     * sorting a stack of dockets is actually looking for.
     *
     * @return array{0: string, 1: string} right, left
     */
    public function titleLine(): array
    {
        $kind = $this->kind();

        if ($destination = $this->destination()) {
            $kind = trim($kind . ' · ' . $destination);
        }

        return [$this->shopName(), $kind];
    }

    /** Address, phone and tax number on one line, or null when there are none. */
    public function contactLine(): ?string
    {
        $lines = $this->shopLines();

        return $lines === [] ? null : implode(' · ', $lines);
    }

    /**
     * The header as paired facts, two to a line.
     *
     * The labels are gone with the line breaks: "ORD-EFQ2AM" and "18/09 21:40"
     * need no telling apart, and a slip is read by someone who knows what a
     * receipt says.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public function headerRows(): array
    {
        $order = $this->order;

        $facts = array_values(array_filter([
            $order->reference,
            $order->created_at?->format('d/m H:i'),
            $order->customer_name ?: null,
            $order->customer_phone ?: null,
            $order->isDineIn() && $order->table_number !== null ? 'طاولة ' . $order->table_number : null,
            $order->paidBy?->name ? 'كاشير: ' . $order->paidBy->name : null,
        ], static fn ($value) => $value !== null && $value !== ''));

        return array_map(
            static fn (array $pair) => [$pair[0], $pair[1] ?? ''],
            array_chunk($facts, 2),
        );
    }

    /**
     * One item on one line: how many, of what, and at what each.
     *
     * The unit price used to sit on a line of its own under the name, which
     * doubled the length of the item block.
     *
     * @param  array{name: string, qty: int, unit: float, total: float}  $item
     */
    public function itemLabel(array $item): string
    {
        $label = $item['qty'] . ' × ' . $item['name'];

        // On a line of one the unit price is the line total, printed twice.
        return $item['qty'] > 1
            ? $label . ' (' . $item['qty'] . ' × ' . number_format($item['unit'], 2) . ')'
            : $label;
    }

    /**
     * Subtotal, discount and delivery on one line.
     *
     * Short labels, because the whole point is that the three fit on a single
     * line: "رسوم التوصيل" spelled out pushes the figure onto a second one and
     * costs the line this merge was meant to save.
     */
    public function totalsLine(): ?string
    {
        $short = [
            'المجموع'      => 'مجموع',
            'الخصم'        => 'خصم',
            'رسوم التوصيل' => 'توصيل',
        ];

        $parts = [];

        foreach ($this->totals() as $label => $amount) {
            $parts[] = ($short[$label] ?? $label) . ' ' . number_format($amount, 2);
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /** The whole delivery address on one line. */
    public function addressLine(): ?string
    {
        $lines = $this->addressLines();

        return $lines === [] ? null : implode(' · ', $lines);
    }

    /** @return array<string, ?string> label => value, for the header block */
    public function header(): array
    {
        $order = $this->order;

        return array_filter([
            'رقم الطلب' => $order->reference,
            'التاريخ'   => $order->created_at?->format('d/m/Y — H:i'),
            'الزبون'    => $order->customer_name ?: null,
            'الهاتف'    => $order->customer_phone ?: null,
            'الطاولة'   => $order->isDineIn() ? $order->table_number : null,
            'الكاشير'   => $order->paidBy?->name,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<int, array{name: string, qty: int, unit: float, total: float, notes: array<int, string>}>
     */
    public function items(): array
    {
        return $this->order->items->map(fn (OrderItem $item) => [
            'name'  => $item->product_name,
            'qty'   => $item->quantity,
            // Per-unit as well as line total: a customer checking a slip adds
            // up the unit prices, and without them the only way to verify a
            // line of three is to divide.
            'unit'  => (float) $item->unit_price + (float) $item->addons_total / max(1, (int) $item->quantity),
            'total' => $item->line_total,
            // The resolved description carries size, flavours and extras, which
            // is exactly what whoever makes the order needs to read.
            'notes' => array_values(array_filter(array_map('trim', explode('+', (string) $item->description)))),
        ])->all();
    }

    /** @return array<string, float> label => amount, zero rows omitted */
    public function totals(): array
    {
        $order = $this->order;

        return array_filter([
            'المجموع'      => $order->subtotal,
            'الخصم'        => -$order->discount,
            'رسوم التوصيل' => $order->delivery_fee,
        ], static fn ($value) => abs((float) $value) > 0.001);
    }

    public function total(): float
    {
        return $this->order->total;
    }

    public function paymentLabel(): string
    {
        return [
            'cash'          => 'نقداً',
            'visa'          => 'بطاقة',
            'wallet'        => 'محفظة',
            'jawwal'        => 'جوال باي',
            'jawwal-manual' => 'جوال باي (تحويل)',
            'bop'           => 'بنك فلسطين',
            'palpay'        => 'PalPay',
        ][$this->order->payment_method] ?? $this->order->payment_method;
    }

    public function paid(): bool
    {
        return $this->order->isPaid();
    }

    /**
     * The driver, for the box that used to hold "غير مدفوع".
     *
     * That banner is gone: it warned about a state the slip is not printed in
     * any more, and the one thing worth a box on a delivery slip is who is
     * carrying it.
     */
    public function driverLine(): ?string
    {
        if ($this->order->delivery_method !== 'delivery' || blank($this->order->driver)) {
            return null;
        }

        $driver = $this->order->driver;

        return trim(implode(' · ', array_filter([
            $driver['name'] ?? null,
            $driver['phone'] ?? null,
        ])));
    }

    /**
     * What the customer handed over, and the change going to their wallet.
     *
     * On the paper because of one specific mistake it prevents: the change is
     * credited, so there are no coins to give back. A cashier reading a total
     * of 36 against a hundred shekel note will hand back 64 out of habit — and
     * the shop has then paid it twice.
     *
     * @return array<string, string> label => rendered value, empty when none
     */
    public function tenderLines(): array
    {
        $change = $this->order->changeDue();

        if ($change <= 0) {
            return [];
        }

        return [
            'المدفوع'          => number_format((float) $this->order->tendered_amount, 2),
            'الباقي (للمحفظة)' => number_format($change, 2),
        ];
    }

    /** @return array<int, string> */
    public function addressLines(): array
    {
        if ($this->order->delivery_method !== 'delivery' || blank($this->order->address)) {
            return [];
        }

        $address = $this->order->address;

        return array_values(array_filter([
            implode('، ', array_filter([$address['city'] ?? null, $address['area'] ?? null])),
            $address['street'] ?? null,
            $address['landmark'] ?? null,
        ]));
    }

    // ─── printer rendering ──────────────────────────────────────────────────

    /**
     * The receipt as printer lines, wrapped to `$width` characters.
     *
     * @return array<int, array{text: string, align?: string, bold?: bool, large?: bool}>
     */
    public function lines(int $width = 48): array
    {
        $out = [];

        $push = function (string $text, array $opts = []) use (&$out) {
            $out[] = ['text' => $text] + $opts;
        };

        $rule = fn () => $push(str_repeat('-', $width), ['align' => 'center']);

        /*
         * Bold, not double-height.
         *
         * A thermal slip is charged by the millimetre of paper and read at
         * arm's length on a counter; every double-height line costs a line of
         * roll for legibility nobody needed. The name and the kind still stand
         * out — weight does that as well as size, in half the space.
         */
        [$name, $kind] = $this->titleLine();
        $push($this->columns($name, $kind, $width), ['bold' => true]);

        if ($contact = $this->contactLine()) {
            foreach ($this->wrap($contact, $width) as $line) {
                $push($line, ['align' => 'center']);
            }
        }

        $rule();

        foreach ($this->headerRows() as [$right, $left]) {
            $push($this->columns($right, $left, $width));
        }

        $rule();

        foreach ($this->items() as $item) {
            $push(
                $this->columns($this->itemLabel($item), number_format($item['total'], 2), $width),
                ['bold' => true],
            );

            foreach ($item['notes'] as $note) {
                // Indented so the extras read as belonging to the line above.
                foreach ($this->wrap('   ' . $note, $width) as $line) {
                    $push($line);
                }
            }
        }

        $rule();

        if ($totals = $this->totalsLine()) {
            foreach ($this->wrap($totals, $width) as $line) {
                $push($line);
            }
        }

        // The total shares its line with how it was paid: the two are read
        // together and neither needs a line to itself.
        $push(
            $this->columns(
                'الإجمالي ' . number_format($this->total(), 2) . ' ₪',
                $this->paymentLabel(),
                $width,
            ),
            ['bold' => true],
        );

        foreach ($this->tenderLines() as $label => $value) {
            $push($this->columns($label, $value, $width));
        }

        if ($this->tenderLines() !== []) {
            // Loud, because it countermands the reflex.
            $push('*** لا تُعِد باقياً نقداً ***', ['align' => 'center', 'bold' => true]);
        }

        // The driver keeps a line of his own on a delivery slip — it is the one
        // thing somebody handing over a bag has to check — but not a box.
        if ($driver = $this->driverLine()) {
            $push('السائق: ' . $driver, ['align' => 'center', 'bold' => true]);
        }

        if ($address = $this->addressLine()) {
            foreach ($this->wrap('العنوان: ' . $address, $width) as $line) {
                $push($line);
            }
        }

        if (filled($this->order->notes)) {
            foreach ($this->wrap('ملاحظة: ' . $this->order->notes, $width) as $line) {
                $push($line, ['bold' => true]);
            }
        }

        // Only on a delivery slip: it is instructions for the person at the door.
        if ($this->order->delivery_method === 'delivery' && filled($this->order->captain_note)) {
            foreach ($this->wrap('للكابتن: ' . $this->order->captain_note, $width) as $line) {
                $push($line);
            }
        }

        $rule();
        $push($this->footer(), ['align' => 'center']);

        return $out;
    }

    /**
     * A label on one side and a value on the other, padded to the paper width.
     *
     * Measured in characters, not bytes: an Arabic label is multi-byte and
     * strlen would pad it into the next line.
     */
    private function columns(string $left, string $right, int $width): string
    {
        // The right-hand value is the number being checked and is never cut;
        // a label too long for what is left of the line is.
        $room = $width - mb_strlen($right) - 1;

        if ($room > 0 && mb_strlen($left) > $room) {
            $left = rtrim(mb_substr($left, 0, $room));
        }

        $gap = $width - mb_strlen($left) - mb_strlen($right);

        return $gap < 1
            ? $left . ' ' . $right
            : $left . str_repeat(' ', $gap) . $right;
    }

    /**
     * Break a run of text into lines the roll can hold, on word boundaries.
     *
     * @return array<int, string>
     */
    private function wrap(string $text, int $width): array
    {
        if (mb_strlen($text) <= $width) {
            return [$text];
        }

        $lines = [];
        $line  = '';

        foreach (explode(' ', $text) as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;

            if (mb_strlen($candidate) <= $width) {
                $line = $candidate;

                continue;
            }

            if ($line !== '') {
                $lines[] = $line;
            }

            // A single word longer than the roll is cut rather than lost.
            while (mb_strlen($word) > $width) {
                $lines[] = mb_substr($word, 0, $width);
                $word    = mb_substr($word, $width);
            }

            $line = $word;
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }
}
