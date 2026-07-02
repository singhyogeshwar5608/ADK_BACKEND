<?php

$defaultOrigins = implode(',', [
    'https://www.offerlifetime.com',
    'https://offerlifetime.com',
    'https://master.d1yeg5lmbstgw1.amplifyapp.com',
    'https://adkpro.netlify.app',
    'http://localhost:5173',
    'http://127.0.0.1:5173',
]);

$corsOriginsRaw = env('CORS_ALLOWED_ORIGINS');
$corsOriginsCsv = ($corsOriginsRaw === null || $corsOriginsRaw === '')
    ? $defaultOrigins
    : $corsOriginsRaw;

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /*
     * If CORS_ALLOWED_ORIGINS is set in .env to an empty string, env() returns "" and a normal env(..., default)
     * would not fall back — we treat "" like unset so defaults still apply.
     */
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', $corsOriginsCsv)))),

    'allowed_origins_patterns' => [
        '#^https?://localhost(:\d+)?$#i',
        '#^https?://127\.0\.0\.1(:\d+)?$#i',
        '#^https?://192\.168\.\d{1,3}\.\d{1,3}(:\d+)?$#i',
    ],

    /*
     * Echo requested headers on preflight (required when clients send Access-Control-Request-Headers
     * e.g. accept, content-type, authorization).
     */
    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => true,

];
