<?php

namespace App\Services\Wallet;

/** Local simulator. No HTTP client, credentials, live bank details or live adapter. */
final class TransferSimulator implements TransferProvider
{
    public function result(object $withdrawal, string $scenario): array
    {
        $status = $this->outcome($scenario);

        return ['simulated' => true, 'reference' => $withdrawal->reference, 'currency' => 'PHP', 'amount_minor' => (int) $withdrawal->amount_minor, 'destination_version' => $withdrawal->destination_version, 'status' => $status,
            'provider_status' => match ($status) {
                'processing' => 'ROUTING','completed' => 'SUCCEEDED','failed' => 'FAILED','reversed' => 'REVERSED'
            }];
    }

    public function outcome(string $status): string
    {
        if (! in_array($status, ['processing', 'completed', 'failed', 'reversed'], true)) {
            throw new \DomainException('Unsupported simulated outcome.');
        }

        return $status;
    }

    public function fee(): int
    {
        return 0;
    } // Simulation policy, not a provider fee quote.
}
