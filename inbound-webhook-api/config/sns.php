<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AWS / SNS connection
    |--------------------------------------------------------------------------
    |
    | The SNS topic this service publishes inbound webhooks to. That topic then
    | fans out to one or more SQS queues (the SNS -> SQS chain) that downstream
    | consumers (e.g. the consolidator's queue worker) read from.
    |
    | `endpoint` is only set for local testing against LocalStack; leave it null
    | in real AWS so the SDK uses the default regional endpoint.
    |
    */

    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),

    'credentials' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        // Set for temporary/STS credentials; null otherwise.
        'token' => env('AWS_SESSION_TOKEN'),
    ],

    // e.g. http://localstack:4566 for local dev; null in production AWS.
    'endpoint' => env('AWS_ENDPOINT_URL'),

    // The SNS topic ARN webhooks are published to.
    'topic_arn' => env('SNS_TOPIC_ARN'),

    /*
    |--------------------------------------------------------------------------
    | Per-source webhook signing secrets
    |--------------------------------------------------------------------------
    |
    | Used to verify inbound webhook signatures. For Shopify this is the app's
    | client secret (API secret key): HMAC-SHA256 of the raw body, base64.
    |
    */

    'sources' => [
        'shopify' => [
            'secret' => env('SHOPIFY_WEBHOOK_SECRET'),
            // Skip verification for local testing only. NEVER true in production.
            'skip_verification' => (bool) env('SHOPIFY_WEBHOOK_SKIP_VERIFY', false),
        ],
    ],
];
