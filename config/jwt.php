<?php

return [
    'secret' => env('JWT_SECRET'),

    'ttl' => env('JWT_TTL', 172800),

    'refresh_ttl' => env('JWT_REFRESH_TTL', 259200),

    'algo' => 'HS256',

    'blacklist_enabled' => true,
];
