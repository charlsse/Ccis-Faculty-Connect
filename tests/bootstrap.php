<?php

// Fail before Laravel can run RefreshDatabase against cached development settings.
if (is_file(__DIR__.'/../bootstrap/cache/config.php')) {
    fwrite(STDERR, "Tests refused: run php artisan config:clear first.\n");
    exit(1);
}

require __DIR__.'/../packages/autoload.php';
