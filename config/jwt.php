<?php

return [
    'secret' => env('JWT_SECRET'),

    'ttl' => 60,

    'refresh_ttl' => 20160,

    'algo' => 'HS256',

    'blacklist_enabled' => true,
];
