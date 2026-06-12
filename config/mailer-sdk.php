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
    */
    'base_url' => env('MAILER_BASE_URL', 'https://api.mailer.test/api/v1'),

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
];
