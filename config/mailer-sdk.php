<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The fully qualified base URL of the Mailer REST API v1, including the
    | "/api/v1" suffix. A trailing slash is allowed and stripped internally.
    |
    | Optional for hosted users: it defaults to the hosted API, so you only
    | need to set MAILER_API_TOKEN. Self-hosted users set MAILER_BASE_URL to
    | their own endpoint. An explicitly empty or placeholder value still makes
    | the MailerClient throw a MailerConfigurationException at construction.
    |
    */
    'base_url' => env('MAILER_BASE_URL', \Mailer\Sdk\MailerClient::DEFAULT_BASE_URL),

    /*
    |--------------------------------------------------------------------------
    | API Token
    |--------------------------------------------------------------------------
    |
    | The project API key (a Sanctum personal access token) used as the Bearer
    | token on every request.
    |
    */
    'token' => env('MAILER_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | HTTP transport / resilience
    |--------------------------------------------------------------------------
    |
    | Tuning for the Guzzle client the SDK builds for you. `timeout` is the
    | per-request timeout (seconds). The retry settings drive the automatic
    | exponential-backoff middleware (delays in milliseconds); retries only
    | apply to requests that are safe to repeat (see the SDK README).
    |
    */
    'timeout' => (int) env('MAILER_TIMEOUT', 10),
    'retries' => (int) env('MAILER_RETRIES', 2),
    'retry_base_delay' => (int) env('MAILER_RETRY_BASE_DELAY', 200),
    'retry_max_delay' => (int) env('MAILER_RETRY_MAX_DELAY', 5000),

    /*
    |--------------------------------------------------------------------------
    | Mail transport (MAIL_MAILER=mailer)
    |--------------------------------------------------------------------------
    |
    | Behavior of the "mailer" Laravel mail driver registered by this package.
    |
    | attachments: how to handle a message that carries attachments.
    |     'send'   — (default) map them onto the /send `attachments` field
    |                (filename, content type, base64 content). Platform limits
    |                apply: max 10 files, 10 MB decoded per file and per send
    |                (the SDK throws an AttachmentsTooLargeException locally
    |                when the total exceeds the per-send cap, before uploading);
    |                executable filename extensions are rejected server-side.
    |                Multi-recipient sends with attachments go out as
    |                per-recipient single sends (/send/batch rejects them).
    |     'fail'   — throw an UnsupportedFeatureException (the send fails).
    |     'ignore' — log a warning and send the message without the attachments.
    |
    | idempotency: how the per-send Idempotency-Key is derived (a message can
    |   still override it with the X-Mailer-Idempotency-Key header).
    |     'content' — deterministic hash of the content, so a queue retry of the
    |                 same job never duplicates the send.
    |     'random'  — a fresh UUID per send attempt (disables retry dedup).
    |     'off'     — no idempotency key.
    |
    */
    'mail' => [
        'attachments' => env('MAILER_MAIL_ATTACHMENTS', 'send'),
        'idempotency' => env('MAILER_MAIL_IDEMPOTENCY', 'content'),
    ],
];
