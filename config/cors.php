<?php

/*
 * O site e o painel na Vercel são sempre aceites; CORS_ALLOWED_ORIGINS
 * acrescenta outras origens (separadas por vírgula). A barra final é
 * removida porque o cabeçalho Origin nunca a traz.
 */
$origens = array_values(array_unique(array_filter(array_map(
    fn (string $origem) => rtrim(trim($origem), '/'),
    [
        'https://joaodomingues.vercel.app',
        ...explode(',', (string) env(
            'CORS_ALLOWED_ORIGINS',
            'http://localhost:5510,http://127.0.0.1:5510,http://localhost:5500,http://127.0.0.1:5500'
        )),
    ]
))));

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $origens,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,

];
