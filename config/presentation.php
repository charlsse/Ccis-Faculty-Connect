<?php

$file = storage_path('app/private/presentation-origin.json');
$origin = env('APP_ENV') !== 'testing' && is_file($file)
    ? (json_decode(file_get_contents($file), true)['origin'] ?? '') : '';

return [
    // Changing this approved origin requires review; never take it from a request.
    'origin' => $origin === 'https://dui-absolutely-beam-plains.trycloudflare.com' ? $origin : '',
];
