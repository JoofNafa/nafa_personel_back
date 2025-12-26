<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*', 'http://35.181.52.41:8000'], // ou ['http://localhost:8080', 'http://10.0.2.2:8080']

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false, // JWT = stateless → pas de cookie

];