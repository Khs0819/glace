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
        'poll_seconds'   => (int) env('GLACE_CASHIER_POLL', 10),
        'lookback_hours' => (int) env('GLACE_CASHIER_LOOKBACK', 12),
        'auto_print'     => (bool) env('GLACE_CASHIER_AUTOPRINT', true),
    ],

];
