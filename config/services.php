<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Jawwal Pay — Service Bus (POS)
    |--------------------------------------------------------------------------
    |
    | Merchant payment gateway. Credentials are issued per environment and are
    | not interchangeable: the production set is only handed over once the
    | sandbox integration is signed off. Reference: "Jawwal Pay System —
    | Service Bus POS Developer Guide v1.0".
    |
    */

    /*
    |--------------------------------------------------------------------------
    | SMS — one-time login codes
    |--------------------------------------------------------------------------
    |
    | No Palestinian SMS gateway has been contracted yet, so `log` is the
    | default: codes are written to the application log and the OTP flow is
    | fully exercisable locally. SmsSender refuses `log` in production, because
    | a code that only reaches storage/logs is not a login.
    |
    | To go live: SMS_DRIVER=http plus SMS_HTTP_URL (and SMS_HTTP_TOKEN if the
    | provider wants one). The field names are configurable because every
    | gateway spells them differently.
    |
    */

    'sms' => [
        // hotsms | smpp | rest | http | log | null
        //
        // `log` writes the code to the application log and is refused in
        // production — see App\Services\Sms\SmsSender.
        'driver'      => env('SMS_DRIVER', 'log'),
        'log_channel' => env('SMS_LOG_CHANNEL'),

        /*
         * The provider's HTTP v1 API — sendbulksms.php.
         *
         * Same account as the others, but authenticated per request, so there
         * is no token to obtain, cache, or renew. That is what makes it the
         * one to run OTP on.
         */
        'hotsms' => [
            'base_url' => env('SMS_API_URL', 'https://hotsms.ps'),
            'username' => env('SMS_API_USERNAME', env('SMPP_USERNAME')),
            'password' => env('SMS_API_PASSWORD', env('SMPP_PASSWORD')),
            'sender'   => env('SMS_SENDER', 'Glace'),
            'timeout'  => (int) env('SMS_API_TIMEOUT', 15),
        ],

        /*
         * Direct SMSC bind. Fewest moving parts between us and the network.
         *
         * `sender` is the alphanumeric name the recipient sees; it has to be
         * one the operator approved for the account, or the SMSC answers
         * ESME_RINVSRCADR (0x0A).
         */
        'smpp' => [
            'host'        => env('SMPP_HOST', '37.58.92.36'),
            'port'        => (int) env('SMPP_PORT', 9999),
            'username'    => env('SMPP_USERNAME'),
            'password'    => env('SMPP_PASSWORD'),
            'sender'      => env('SMS_SENDER', 'Glace'),
            'system_type' => env('SMPP_SYSTEM_TYPE', ''),
            'timeout'     => (int) env('SMPP_TIMEOUT', 15),
        ],

        /*
         * The provider's REST v2 API.
         *
         * The token is NOT obtained automatically: their login texts a PIN to
         * the account owner, which no server can answer. Run
         * `php artisan sms:login` once and put the result in SMS_API_TOKEN.
         */
        'rest' => [
            'base_url' => env('SMS_API_URL', 'https://hotsms.ps'),
            'token'    => env('SMS_API_TOKEN'),
            'sender'   => env('SMS_SENDER', 'Glace'),
            'username' => env('SMS_API_USERNAME', env('SMPP_USERNAME')),
            'password' => env('SMS_API_PASSWORD', env('SMPP_PASSWORD')),
            'timeout'  => (int) env('SMS_API_TIMEOUT', 15),
        ],

        /* Any other provider that takes a plain JSON POST. */
        'http' => [
            'url'        => env('SMS_HTTP_URL'),
            'token'      => env('SMS_HTTP_TOKEN'),
            'to_field'   => env('SMS_HTTP_TO_FIELD', 'to'),
            'body_field' => env('SMS_HTTP_BODY_FIELD', 'message'),
            'timeout'    => (int) env('SMS_HTTP_TIMEOUT', 15),
        ],
    ],

    'jawwalpay' => [
        'enabled'  => (bool) env('JAWWALPAY_ENABLED', false),
        'base_url' => env('JAWWALPAY_BASE_URL', 'https://apitest.jawwalpay.ps'),
        'username' => env('JAWWALPAY_USERNAME'),
        'password' => env('JAWWALPAY_PASSWORD'),

        // HMAC secret for secureHash. Issued separately from the login
        // credentials; the guide calls it both "apiKey" and "HMAC secret".
        'secret' => env('JAWWALPAY_SECRET'),

        // Sandbox wallet the provider gave us to test against. Only ever a
        // target for `jawwalpay:check --otp`; nothing in the app reads it.
        'test_wallet' => env('JAWWALPAY_TEST_WALLET'),

        'lang'            => env('JAWWALPAY_LANG', 'AR'),
        'timeout'         => (int) env('JAWWALPAY_TIMEOUT', 30),
        'connect_timeout' => (int) env('JAWWALPAY_CONNECT_TIMEOUT', 10),

        // Ceiling for how long a login token is cached. The token carries its
        // own exp claim and that wins whenever it is shorter.
        'token_ttl' => (int) env('JAWWALPAY_TOKEN_TTL', 3600),

        // secureHash shape. Guide §3 step 1 says "sort by key" while its own
        // worked example sorts by value — and the hash it prints reproduces
        // under neither. Switchable so the sandbox can settle it without a
        // code change. See App\Services\JawwalPay\SecureHash.
        'hash_algo' => env('JAWWALPAY_HASH_ALGO', 'sha512'),
        'hash_sort' => env('JAWWALPAY_HASH_SORT', 'value'), // value|key

        'log' => (bool) env('JAWWALPAY_LOG', true),
    ],

];
