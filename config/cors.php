<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*', 'https://unclean-interorbitally-ashton.ngrok-free.dev'], // ou ['http://localhost:8080', 'http://10.0.2.2:8080']

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false, // JWT = stateless → pas de cookie

];