<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery restrictions (handoff 12 §3)
    |--------------------------------------------------------------------------
    |
    | Some products do not survive the trip. The storefront mirrors these rules
    | in src/lib/deliveryRestrictions.ts so a customer cannot pick an invalid
    | combination; this copy is what actually enforces them, because a request
    | need not have come from that form.
    |
    | Keep the two in step. If a rule changes here and not there, the customer
    | sees a checkout that accepts an order the server then rejects.
    |
    */

    'delivery' => [

        // Never deliverable, whatever size or options were chosen.
        'blocked_products' => [
            'gelatodome',
        ],

        // Deliverable in some sizes but not others. Keyed by product slug; the
        // values are matched against the size's slug first and its label second.
        //
        // Anything with `in_store_only` set in the dashboard is refused too,
        // and does not need listing here.
        'restricted_sizes' => [
            'brad'      => ['small', 'medium', 'صغير', 'وسط'],
            'brad-boza' => ['small', 'medium', 'صغير', 'وسط'],
            'cup'       => ['small', 'medium', 'صغير', 'وسط'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Preparation time
    |--------------------------------------------------------------------------
    |
    | Minutes, shown on the order tracker. The shop sets the real figure per
    | order from the dashboard (handoff 12 §4) — these are only the defaults a
    | brand-new order starts with, so the tracker has something to show before
    | anyone has looked at it.
    |
    */

    'preparation_time' => (int) env('GLACE_PREPARATION_MINUTES', 15),

    'estimated_delivery_time' => (int) env('GLACE_DELIVERY_MINUTES', 20),

    /*
    |--------------------------------------------------------------------------
    | Shop identity — printed on every receipt
    |--------------------------------------------------------------------------
    */

    'shop' => [
        'name'       => env('GLACE_SHOP_NAME', 'جلاسيه الأمير'),
        'address'    => env('GLACE_SHOP_ADDRESS'),
        'phone'      => env('GLACE_SHOP_PHONE'),
        'tax_number' => env('GLACE_SHOP_TAX_NUMBER'),
        'footer'     => env('GLACE_RECEIPT_FOOTER', 'شكراً لزيارتكم'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Receipt printing
    |--------------------------------------------------------------------------
    |
    | Two paths, and both are wanted:
    |
    |   · The network printer pushes a receipt the moment an order lands, with
    |     nobody watching a screen. Needs a fixed IP on the same network as the
    |     server. Leave GLACE_PRINTER_ENABLED=false and it is simply skipped.
    |
    |   · The cashier screen picks up whatever the printer did not get and
    |     prints through the browser. Always available, and how a reprint works.
    |
    | `codepage` is a property of the hardware, not a preference:
    |   CP864  — the printer expects Arabic presentation forms; we shape first.
    |   CP1256 — the printer shapes for itself; we must NOT shape, or it doubles.
    | If Arabic prints as disconnected or reversed letters, this is the setting.
    |
    | `width` is characters per line: 48 on an 80 mm head, 32 on a 58 mm one.
    |
    */

    'printer' => [
        'enabled'     => (bool) env('GLACE_PRINTER_ENABLED', false),
        'host'        => env('GLACE_PRINTER_HOST'),
        'port'        => (int) env('GLACE_PRINTER_PORT', 9100),
        'timeout'     => (int) env('GLACE_PRINTER_TIMEOUT', 5),
        'codepage'    => env('GLACE_PRINTER_CODEPAGE', 'CP864'),

        // The `ESC t n` index for that code page. Left blank we guess from the
        // name using Epson's numbering, which is right on an Epson and wrong
        // on most other makes — a Bixolon SRP-330 puts the same code page
        // somewhere else entirely. Run `php artisan printer:codepages` and read
        // the number off the paper.
        'codepage_table' => env('GLACE_PRINTER_CODEPAGE_TABLE'),

        // The browser receipt. Printer drivers keep their own unprintable
        // margin, and on some the right edge is wider than the datasheet says:
        // anything drawn there — the quantity at the start of an Arabic line —
        // is lost. So the content is drawn narrower and pushed off the right
        // edge. If digits still clip, raise the margin; if the left clips,
        // lower the width.
        'receipt_width_80'     => (float) env('GLACE_RECEIPT_WIDTH_80', 66),
        'receipt_width_58'     => (float) env('GLACE_RECEIPT_WIDTH_58', 44),
        'receipt_right_margin' => (float) env('GLACE_RECEIPT_RIGHT_MARGIN', 5),
        'width'       => (int) env('GLACE_PRINTER_WIDTH', 48),
        'cut'         => (bool) env('GLACE_PRINTER_CUT', true),
        'open_drawer' => (bool) env('GLACE_PRINTER_DRAWER', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cashier screen
    |--------------------------------------------------------------------------
    |
    | How often the counter screen asks for new orders, and how far back it
    | looks on first load. A tight poll keeps the queue live; too tight and it
    | is a query per second per open till.
    |
    */

    'cashier' => [
        // Seconds between refreshes of the cashier screen. Short on purpose:
        // the screen is the counter's live queue, and a new order must appear
        // while the customer is still standing there.
        'poll_seconds'   => (int) env('GLACE_CASHIER_POLL', 3),
        'lookback_hours' => (int) env('GLACE_CASHIER_LOOKBACK', 12),
        // Off: the cashier prints each receipt by pressing its button. On, a
        // receipt is sent to the network printer as soon as an order lands.
        'auto_print'     => (bool) env('GLACE_CASHIER_AUTOPRINT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Money ceilings
    |--------------------------------------------------------------------------
    |
    | Two limits the shop asked for at launch, both about money leaving rather
    | than money arriving:
    |
    |   max_change — the most change a cash payment may produce, whether it
    |   goes to the customer's wallet or back as a transfer. A cashier typing
    |   500 against a 36 shekel order is a typo, not a banknote.
    |
    |   max_topup — the most one top-up request may ask for. A request is only
    |   ever credited after a human matches it to a transfer, but the ceiling
    |   keeps a mistyped one from reaching that desk at all.
    |
    */

    'limits' => [
        'max_change' => (float) env('GLACE_MAX_CHANGE', 199),
        'max_topup'  => (float) env('GLACE_MAX_TOPUP', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Opening hours timezone
    |--------------------------------------------------------------------------
    |
    | Opening hours are read in the shop's local time. The application itself
    | runs in UTC, and comparing hours against UTC would open and close the
    | shop two or three hours off.
    |
    */

    'timezone' => env('GLACE_TIMEZONE', 'Asia/Gaza'),

];
