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

    /** @return array<int, array{name: string, qty: int, total: float, notes: array<int, string>}> */
    public function items(): array
    {
        return $this->order->items->map(fn (OrderItem $item) => [
            'name'  => $item->product_name,
            'qty'   => $item->quantity,
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
            'paypal'        => 'PayPal',
        ][$this->order->payment_method] ?? $this->order->payment_method;
    }

    /** Unpaid orders say so on the paper, so nobody hands one over by mistake. */
    public function paid(): bool
    {
        return $this->order->isPaid();
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

        $push($this->shopName(), ['align' => 'center', 'bold' => true, 'large' => true]);

        foreach ($this->shopLines() as $line) {
            $push($line, ['align' => 'center']);
        }

        $rule();

        $push($this->kind(), ['align' => 'center', 'bold' => true, 'large' => true]);

        if ($destination = $this->destination()) {
            $push($destination, ['align' => 'center', 'bold' => true]);
        }

        $rule();

        foreach ($this->header() as $label => $value) {
            $push($this->columns($label, (string) $value, $width));
        }

        $rule();

        foreach ($this->items() as $item) {
            $push(
                $this->columns(
                    $item['qty'] . ' × ' . $item['name'],
                    number_format($item['total'], 2),
                    $width,
                ),
                ['bold' => true],
            );

            foreach ($item['notes'] as $note) {
                // Indented so the extras read as belonging to the line above.
                $push('   ' . $note);
            }
        }

        $rule();

        foreach ($this->totals() as $label => $amount) {
            $push($this->columns($label, number_format($amount, 2), $width));
        }

        $push(
            $this->columns('الإجمالي', number_format($this->total(), 2) . ' ILS', $width),
            ['bold' => true, 'large' => true],
        );

        $push($this->columns('الدفع', $this->paymentLabel(), $width));

        if (! $this->paid()) {
            $push('*** غير مدفوع ***', ['align' => 'center', 'bold' => true]);
        }

        if ($lines = $this->addressLines()) {
            $rule();
            $push('عنوان التوصيل', ['bold' => true]);

            foreach ($lines as $line) {
                $push($line);
            }
        }

        if (filled($this->order->notes)) {
            $rule();
            $push('ملاحظات: ' . $this->order->notes);
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
        $gap = $width - mb_strlen($left) - mb_strlen($right);

        return $gap < 1
            ? $left . ' ' . $right
            : $left . str_repeat(' ', $gap) . $right;
    }
}
