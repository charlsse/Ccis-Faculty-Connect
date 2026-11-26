<?php

return [
    'enabled' => env('TUNNEL_ENABLED', false),
    // Exact hostname only, never a wildcard. Public access remains webhook/return only.
    'host' => env('TUNNEL_HOST', ''),
];
