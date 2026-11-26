<?php

namespace App\Services\Wallet;

/** Normalized result boundary. A real adapter requires separate review and authorization. */
interface TransferProvider
{
    public function result(object $withdrawal, string $scenario): array;

    public function fee(): int;
}
