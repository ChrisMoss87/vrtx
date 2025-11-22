<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:5173',
        'http://localhost:4173',
        'https://app.vrtx.local'
    ],
    'allowed_origins_patterns' => [
        '/^http:\/\/.*\.vrtx\.local$/',
        '/^https:\/\/.*\.vrtx\.local$/',
        '/^http:\/\/crm\.startup\.com$/',
        '/^https:\/\/crm\.startup\.com$/',
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
