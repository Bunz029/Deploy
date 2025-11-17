<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:8081',
        'http://127.0.0.1:8081',
        'orchid-fly-423221.hostingersite.com',
        'https://isuecampusmap.site',
        '*'
    ],
    // Allow any Netlify and Railway subdomain (optional, safer to list exact domains above)
    'allowed_origins_patterns' => [
        '#^https:\/\/[a-z0-9-]+\.netlify\.app$#i',
        '#^https:\/\/[a-z0-9-]+\.railway\.app$#i'
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['ETag', 'Cache-Control'],
    'max_age' => 0,
    'supports_credentials' => false,
];