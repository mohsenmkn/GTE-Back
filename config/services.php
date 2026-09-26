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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'msgway' => [
        'api_key' => env('MSGWAY_API_KEY'),
        'provider' => env('MSGWAY_PROVIDER', 1747285713),
        'template_id_default' => env('MSGWAY_TEMPLATE_ID_DEFAULT', 23490),
        'template_id_approval' => env('MSGWAY_TEMPLATE_ID_APPROVAL'),
        'template_id_reminder' => env('MSGWAY_TEMPLATE_ID_REMINDER'),
        'template_id_overdue' => env('MSGWAY_TEMPLATE_ID_OVERDUE'),
    ],

];
