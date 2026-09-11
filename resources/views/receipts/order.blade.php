{{--
    A thermal receipt, drawn by the browser.

    Sized in millimetres so it comes out of an 80 mm (or 58 mm) head at the
    right width whatever the screen DPI. @page removes the margins a browser
    would otherwise add, which on roll paper is the difference between a tidy
    docket and one wrapping onto a second sheet.

    Everything is greyscale and heavy-weight: thermal heads have no colour and
    lose thin strokes.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<title>فاتورة {{ $doc->order->reference }}</title>
<style>
    @page { size: {{ $width }}mm auto; margin: 0; }

    * { box-sizing: border-box; }

    html, body {
        margin: 0;
        padding: 0;
        background: #fff;
        color: #000;
    }

    /*
     * The printable width, not the paper width.
     *
     * A thermal head does not reach the edges of its roll: 80 mm paper prints
     * 72 mm, 58 mm paper prints 48 mm. Drawing the page at the full paper width
     * puts the outer millimetres outside the head, and in a right-to-left
     * layout that is exactly where the order number, date and phone sit — so
     * they came out cut off. Centred, so the driver's own offset does not
     * matter.
     */
    body {
        width: {{ $width >= 80 ? 72 : 48 }}mm;
        margin: 0 auto;
        padding: 1mm 1mm 0;
        /* A monospace stack keeps the two-column rows aligned; the Arabic
           faces are named first so they win for Arabic glyphs. */
        font-family: "Tahoma", "Arial", "Segoe UI", monospace;
        font-size: {{ $width >= 80 ? '12px' : '11px' }};
        line-height: 1.45;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .center { text-align: center; }
    .bold   { font-weight: 700; }

    /* Trimmed deliberately: every millimetre here is a millimetre of roll,
       and the slip is read at arm's length on a counter, not across a room. */
    .shop   { font-size: {{ $width >= 80 ? '15px' : '13px' }}; font-weight: 700; }
    .kind   { font-size: {{ $width >= 80 ? '14px' : '12px' }}; font-weight: 700; }
    .dest   { font-size: {{ $width >= 80 ? '13px' : '12px' }}; font-weight: 700; }
    .item-unit { font-size: 0.85em; opacity: .75; }

    hr {
        border: 0;
        border-top: 1px dashed #000;
        margin: 1.2mm 0;
    }

    .row {
        display: flex;
        justify-content: space-between;
        gap: 2mm;
    }

    /* The amount must never wrap or shrink — it is the number being checked. */
    .row .amount { white-space: nowrap; font-variant-numeric: tabular-nums; }

    .item      { margin-top: 1mm; font-weight: 700; }
    .item-note { padding-inline-start: 4mm; font-weight: 400; font-size: 0.9em; }

    .total {
        font-size: {{ $width >= 80 ? '15px' : '13px' }};
        font-weight: 700;
        border-top: 2px solid #000;
        padding-top: 1.5mm;
        margin-top: 1.5mm;
    }

    .driver-box {
        margin-top: 2mm;
        padding: 1.5mm;
        text-align: center;
        font-weight: 700;
        border: 2px solid #000;
    }

    .footer { margin-top: 2mm; }

    /* Controls are for the screen only; they must never reach the paper. */
    .controls { margin: 4mm 0; text-align: center; }
    .controls button {
        font: inherit;
        padding: 2mm 4mm;
        cursor: pointer;
    }
    @media print { .controls { display: none !important; } }
</style>
</head>
<body>

<div class="center shop">{{ $doc->shopName() }}</div>
@foreach ($doc->shopLines() as $line)
    <div class="center">{{ $line }}</div>
@endforeach

<hr>

<div class="center kind">{{ $doc->kind() }}</div>
@if ($destination = $doc->destination())
    <div class="center dest">{{ $destination }}</div>
@endif

<hr>

@foreach ($doc->header() as $label => $value)
    <div class="row"><span>{{ $label }}</span><span class="bold">{{ $value }}</span></div>
@endforeach

<hr>

@foreach ($doc->items() as $item)
    <div class="row item">
        <span>{{ $item['qty'] }} × {{ $item['name'] }}</span>
        <span class="amount">{{ number_format($item['total'], 2) }}</span>
    </div>
    @if ($item['qty'] > 1)
        {{-- A line of three cannot be checked against the menu without it. --}}
        <div class="row item-unit">
            <span></span>
            <span class="amount">{{ $item['qty'] }} × {{ number_format($item['unit'], 2) }}</span>
        </div>
    @endif
    @foreach ($item['notes'] as $note)
        <div class="item-note">{{ $note }}</div>
    @endforeach
@endforeach

<hr>

@foreach ($doc->totals() as $label => $amount)
    <div class="row"><span>{{ $label }}</span><span class="amount">{{ number_format($amount, 2) }}</span></div>
@endforeach

<div class="row total">
    <span>الإجمالي</span>
    <span class="amount">{{ number_format($doc->total(), 2) }} ₪</span>
</div>

<div class="row" style="margin-top:1.5mm">
    <span>الدفع</span><span class="bold">{{ $doc->paymentLabel() }}</span>
</div>

@if ($driver = $doc->driverLine())
    {{-- The box the "غير مدفوع" banner used to have. On a delivery slip the
         thing worth seeing at a glance is who is carrying it. --}}
    <div class="driver-box">السائق: {{ $driver }}</div>
@endif

@if ($lines = $doc->addressLines())
    <hr>
    <div class="bold">عنوان التوصيل</div>
    @foreach ($lines as $line)
        <div>{{ $line }}</div>
    @endforeach
@endif

@if (filled($doc->order->notes))
    <hr>
    <div><span class="bold">ملاحظات:</span> {{ $doc->order->notes }}</div>
@endif

<hr>
<div class="center footer">{{ $doc->footer() }}</div>

@if ($doc->order->print_count > 0)
    {{-- Marks a duplicate so a reprint cannot be passed off as a second sale. --}}
    <div class="center">— نسخة مُعادة —</div>
@endif

<div class="controls">
    <button onclick="window.print()">طباعة</button>
    <button onclick="window.close()">إغلاق</button>
</div>

@if ($autoPrint)
<script>
    // Wait for layout before printing, or the dialog can open against a
    // half-drawn page and produce a blank slip.
    window.addEventListener('load', function () {
        window.setTimeout(function () {
            window.print();
        }, 250);
    });

    // Opened by the cashier screen in a background window: close it once the
    // dialog is done so dockets do not pile up as open tabs.
    window.addEventListener('afterprint', function () {
        if (window.opener) { window.close(); }
    });
</script>
@endif

</body>
</html>
