<?php

$origens = array_values(array_filter(array_map(
    trim(...),
    explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'https://joaodomingues.vercel.app,http://localhost:5510,http://127.0.0.1:5510,http://localhost:5500,http://127.0.0.1:5500'
    ))
)));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origens,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
