<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // El frontend es un Static Site en Render, en otro dominio.
    'allowed_origins' => array_filter(
        explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173'))
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Se usan tokens Bearer, no cookies de sesion.
    'supports_credentials' => false,
];
