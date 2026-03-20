<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie','storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS','http://localhost:5173,http://127.0.0.1:5173,http://localhost:4000,http://localhost:52456'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
