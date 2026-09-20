{{--
    A thermal receipt, drawn by the browser.

    Sized in millimetres so it comes out of an 80 mm (or 58 mm) head at the
    right width whatever the screen DPI. @page removes the margins a browser
    would otherwise add, which on roll paper is the difference between a tidy
    docket and one wrapping onto a second sheet.

    Paper is expensive, so the layout is built to be short: facts that belong
    together share a line, and nothing is printed twice. The pairings come from
    ReceiptDocument, so the printer path prints the same slip.

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
     * Narrower than the printable width, and held off the right edge.
     *
     * A thermal head does not reach the edges of its roll, and the printer
     * driver adds its own margin on top — wider on the right than the
     * datasheet admits. In an Arabic slip the right edge is where every line
     * starts: the quantity, the first letters of each item. Centring the page
     * still lost them, so the content is drawn narrower and pushed left by a
     * set margin. Both numbers are settings (GLACE_RECEIPT_*), so a different
     * printer is tuned without touching this file.
     */
    @php
        $contentWidth = (float) config($width >= 80 ? 'storefront.printer.receipt_width_80' : 'storefront.printer.receipt_width_58', $width >= 80 ? 66 : 44);
        $rightMargin  = (float) config('storefront.printer.receipt_right_margin', 5);
    @endphp
    body {
        width: {{ $contentWidth }}mm;
        margin: 0 {{ $rightMargin }}mm 0 auto;
        padding: 0;
        /* A monospace stack keeps the two-column rows aligned; the Arabic
           faces are named first so they win for Arabic glyphs. */
        font-family: "Tahoma", "Arial", "Segoe UI", monospace;
        font-size: {{ $width >= 80 ? '12px' : '11px' }};
        /* Tight, but not so tight that Arabic descenders touch: every tenth
           here is millimetres of roll across a day of orders. */
        line-height: 1.22;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .center { text-align: center; }
    .bold   { font-weight: 700; }
    .small  { font-size: 0.88em; }

    .shop { font-size: {{ $width >= 80 ? '13px' : '12px' }}; font-weight: 400; }
    .kind { font-size: {{ $width >= 80 ? '14px' : '12px' }}; font-weight: 700; }

    hr {
        border: 0;
        border-top: 1px dashed #000;
        margin: 0.8mm 0;
    }

    .row {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 2mm;
    }

    /* The amount must never wrap or shrink — it is the number being checked. */
    .row .amount { white-space: nowrap; font-variant-numeric: tabular-nums; }

    .item      { font-weight: 700; }
    .item-note { padding-inline-start: 3mm; font-weight: 400; font-size: 0.88em; }

    .total {
        font-size: {{ $width >= 80 ? '14px' : '12px' }};
        font-weight: 700;
        border-top: 2px solid #000;
        padding-top: 0.8mm;
        margin-top: 0.8mm;
    }

    /* A line, not a box: the driver still stands out, at a third of the paper. */
    .driver {
        margin-top: 0.8mm;
        padding-top: 0.8mm;
        border-top: 1px solid #000;
        text-align: center;
        font-weight: 700;
    }

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

@php([$shopName, $kindLine] = $doc->titleLine())

{{-- Shop on the right, kind of order on the left: one line instead of four. --}}
<div class="row">
    <span class="shop">{{ $shopName }}</span>
    <span class="kind">{{ $kindLine }}</span>
</div>

@if ($contact = $doc->contactLine())
    <div class="center small">{{ $contact }}</div>
@endif

<hr>

@foreach ($doc->headerRows() as [$right, $left])
    <div class="row"><span class="bold">{{ $right }}</span><span>{{ $left }}</span></div>
@endforeach

<hr>

@foreach ($doc->items() as $item)
    <div class="row item">
        <span>{{ $doc->itemLabel($item) }}</span>
        <span class="amount">{{ number_format($item['total'], 2) }}</span>
    </div>
    @foreach ($item['notes'] as $note)
        <div class="item-note">{{ $note }}</div>
    @endforeach
@endforeach

<hr>

@if ($totals = $doc->totalsLine())
    <div class="small">{{ $totals }}</div>
@endif

{{-- The total and how it was paid are read together, so they share a line. --}}
<div class="row total">
    <span>الإجمالي {{ number_format($doc->total(), 2) }} ₪</span>
    <span>{{ $doc->paymentLabel() }}</span>
</div>

@foreach ($doc->tenderLines() as $label => $value)
    <div class="row small"><span>{{ $label }}</span><span class="amount">{{ $value }}</span></div>
@endforeach

@if ($doc->tenderLines() !== [])
    <div class="center bold">*** لا تُعِد باقياً نقداً ***</div>
@endif

@if ($driver = $doc->driverLine())
    <div class="driver">السائق: {{ $driver }}</div>
@endif

@if ($address = $doc->addressLine())
    <div class="small"><span class="bold">العنوان:</span> {{ $address }}</div>
@endif

@if (filled($doc->order->notes))
    <div><span class="bold">ملاحظة:</span> {{ $doc->order->notes }}</div>
@endif

@if ($doc->order->delivery_method === 'delivery' && filled($doc->order->captain_note))
    <div class="small"><span class="bold">للكابتن:</span> {{ $doc->order->captain_note }}</div>
@endif

<hr>

{{-- Footer and the duplicate marker share the last line; the marker is what
     stops a reprint being passed off as a second sale. --}}
<div class="center small">
    {{ $doc->footer() }}@if ($doc->order->print_count > 0) · — نسخة مُعادة —@endif
</div>

<div class="controls">
    <button onclick="window.print()">طباعة</button>
    <button onclick="window.close()">إغلاق</button>
</div>

@if ($autoPrint)
<script>
    (function () {
        var reference = "{{ $doc->order->reference }}";

        // The cashier screen prints one receipt at a time and reports each
        // result, so the slip says which order it is and where it has got to.
        function tell(state) {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ receipt: reference, state: state }, window.location.origin);
            }
        }

        // Wait for layout before printing, or the job can start against a
        // half-drawn page and produce a blank slip.
        var reported = false;

        function done() {
            if (reported) { return; }
            reported = true;
            tell('printed');
            if (window.opener) { window.close(); }
        }

        window.addEventListener('load', function () {
            window.setTimeout(function () {
                tell('printing');
                window.print();

                // print() only returns once the job has gone to the printer (or
                // the dialog was closed), so this is the reliable moment to
                // report. The afterprint event below is kept as a backup, but
                // it is not always delivered from inside a frame.
                done();
            }, 250);
        });

        window.addEventListener('afterprint', done);
    })();
</script>
@endif

</body>
</html>
