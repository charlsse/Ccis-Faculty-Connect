<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use App\Services\DataProtectionService;
use App\Services\TwoFactorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;
use Throwable;

class ProtectSensitiveData extends Command
{
    protected $signature = 'facultyconnect:protect-data
        {--dry-run : Report required changes without writing data}
        {--verify : Verify encryption and lookup integrity without writing data}
        {--chunk=100 : Number of records processed per database chunk}
        {--allow-plaintext : Confirm that candidate plaintext was reviewed against a trusted backup}
        {--force : Confirm a production data conversion}';

    protected $description = 'Encrypt or verify FacultyConnect sensitive data without displaying protected values';

    /** @var array<string, list<string>> */
    private array $fields = [
        'users' => ['contact_number', 'bio', 'two_factor_secret', 'two_factor_recovery_codes'],
        'payments' => ['reference_no', 'notes'],
    ];

    /** @var array<string, int> */
    private array $summary = [
        'rows_scanned' => 0,
        'rows_updated' => 0,
        'fields_encrypted' => 0,
        'empty_values_normalized' => 0,
        'lookups_updated' => 0,
        'needs_migration' => 0,
        'corrupted' => 0,
        'failures' => 0,
    ];

    public function handle(
        DataProtectionService $protection,
        TwoFactorService $twoFactor,
        AuditLogger $auditLogger,
    ): int {
        $this->summary = array_fill_keys(array_keys($this->summary), 0);
        $dryRun = (bool) $this->option('dry-run');
        $verifyOnly = (bool) $this->option('verify');
        $writesEnabled = ! $dryRun && ! $verifyOnly;
        $chunkSize = max(1, min(5000, (int) $this->option('chunk')));

        if ($dryRun && $verifyOnly) {
            $this->error('Choose either --dry-run or --verify, not both.');

            return self::INVALID;
        }

        if ($writesEnabled && app()->environment('production') && ! $this->option('force')) {
            $this->error('Production conversion requires --force after a verified database backup.');

            return self::INVALID;
        }

        if ($writesEnabled && app()->environment('production') && ! app()->isDownForMaintenance()) {
            $this->error('Production conversion requires maintenance mode and paused workers.');

            return self::FAILURE;
        }
        if (! $this->schemaIsReady()) {
            return self::FAILURE;
        }

        $mode = $dryRun ? 'dry run' : ($verifyOnly ? 'verification' : 'conversion');
        $this->info("Starting sensitive-data {$mode}; protected values will not be displayed.");

        foreach ($this->fields as $table => $fields) {
            DB::table($table)
                ->select(['id', ...$fields, ...($table === 'payments' ? ['reference_no_lookup'] : [])])
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use ($table, $fields, $writesEnabled, $protection, $twoFactor): void {
                    $firstId = null;
                    $lastId = null;

                    foreach ($rows as $row) {
                        $firstId ??= $row->id;
                        $lastId = $row->id;
                        $this->summary['rows_scanned']++;

                        try {
                            $updates = $this->updatesForRow(
                                $table,
                                $fields,
                                $row,
                                $writesEnabled,
                                $protection,
                                $twoFactor,
                            );

                            if ($writesEnabled && $updates !== []) {
                                DB::transaction(function () use ($table, $row, $fields, $updates, $protection): void {
                                    $current = DB::table($table)->where('id', $row->id)->lockForUpdate()->first();
                                    foreach ($fields as $field) {
                                        if (! $current || $current->$field !== $row->$field) {
                                            throw new \RuntimeException('Row changed during conversion; retry.');
                                        }
                                    }
                                    DB::table($table)->where('id', $row->id)->update($updates);
                                    $saved = DB::table($table)->where('id', $row->id)->first();
                                    foreach ($updates as $field => $value) {
                                        if ($value !== null && $field !== 'reference_no_lookup') {
                                            $protection->decrypt($saved->$field);
                                        }
                                    }
                                });
                                $this->summary['rows_updated']++;
                            }
                        } catch (Throwable $exception) {
                            $this->summary['failures']++;
                            $this->error("{$table} record #{$row->id}: failed safely (".$exception::class.').');
                        }
                    }

                    if ($firstId !== null) {
                        $this->line("Processed {$table} record IDs {$firstId}-{$lastId}.");
                    }
                });
        }

        $this->table(
            ['Check', 'Count'],
            collect($this->summary)->map(fn (int $count, string $label): array => [$label, $count])->values()->all(),
        );

        $hasFailures = $this->summary['corrupted'] > 0 || $this->summary['failures'] > 0;
        $needsMigration = $this->summary['needs_migration'] > 0;

        if ($writesEnabled && ! $hasFailures) {
            $auditLogger->record('data_protection.migration_completed', details: [
                'mode' => $mode,
                'rows_scanned' => $this->summary['rows_scanned'],
                'rows_updated' => $this->summary['rows_updated'],
                'fields_encrypted' => $this->summary['fields_encrypted'],
                'corrupted_count' => $this->summary['corrupted'],
                'failure_count' => $this->summary['failures'],
            ]);
        }

        if ($hasFailures || (($dryRun || $verifyOnly) && $needsMigration)) {
            $this->warn('Data protection checks found records requiring attention. No protected values were printed.');

            return $hasFailures || $verifyOnly ? self::FAILURE : self::SUCCESS;
        }

        $this->info($writesEnabled
            ? 'Sensitive-data conversion completed successfully. Run again with --verify.'
            : 'All inspected sensitive data passed verification.');

        return self::SUCCESS;
    }

    private function schemaIsReady(): bool
    {
        foreach ($this->fields as $table => $fields) {
            if (! Schema::hasTable($table)) {
                $this->error("Required table {$table} does not exist.");

                return false;
            }

            foreach ($fields as $field) {
                if (! Schema::hasColumn($table, $field)) {
                    $this->error("Required protected column {$table}.{$field} does not exist.");

                    return false;
                }
            }
        }

        if (! Schema::hasColumn('payments', 'reference_no_lookup')) {
            $this->error('Run the data-protection schema migration before converting records.');

            return false;
        }

        return true;
    }

    /** @param list<string> $fields */
    private function updatesForRow(
        string $table,
        array $fields,
        object $row,
        bool $writesEnabled,
        DataProtectionService $protection,
        TwoFactorService $twoFactor,
    ): array {
        $updates = [];
        $plainPaymentReference = null;

        foreach ($fields as $field) {
            $value = $row->{$field};
            $state = $protection->classify($value);

            if ($state === DataProtectionService::STATE_EMPTY) {
                if ($table === 'payments' && $field === 'reference_no') {
                    $this->summary['failures']++;
                } elseif ($value === '') {
                    $this->summary['needs_migration']++;
                    if ($writesEnabled) {
                        $updates[$field] = null;
                        $this->summary['empty_values_normalized']++;
                    }
                }

                continue;
            }

            if ($state === DataProtectionService::STATE_CORRUPTED) {
                $this->summary['corrupted']++;

                continue;
            }

            if ($state === DataProtectionService::STATE_PLAINTEXT && $writesEnabled && ! $this->option('allow-plaintext')) {
                throw new \RuntimeException('Candidate plaintext requires --allow-plaintext after operator review.');
            }

            $plain = $state === DataProtectionService::STATE_ENCRYPTED
                ? $protection->decrypt($value)
                : $value;

            $requiresEncryption = $state === DataProtectionService::STATE_PLAINTEXT;

            if ($field === 'two_factor_recovery_codes') {
                $normalizedRecoveryCodes = $this->validatedRecoveryCodeJson($plain, $twoFactor);
                $requiresEncryption = $requiresEncryption || ! hash_equals($plain, $normalizedRecoveryCodes);
                $plain = $normalizedRecoveryCodes;
            }

            if ($table === 'payments' && $field === 'reference_no') {
                $plainPaymentReference = $plain;
            }

            if ($requiresEncryption) {
                $this->summary['needs_migration']++;

                if ($writesEnabled) {
                    $updates[$field] = $protection->encrypt($plain);
                    $this->summary['fields_encrypted']++;
                }
            }
        }

        if ($table === 'payments' && $plainPaymentReference !== null) {
            $lookup = $protection->paymentReferenceLookup($plainPaymentReference);

            if (! hash_equals($lookup, (string) $row->reference_no_lookup)) {
                $this->summary['needs_migration']++;

                if ($writesEnabled) {
                    $updates['reference_no_lookup'] = $lookup;
                    $this->summary['lookups_updated']++;
                }
            }
        }

        return $updates;
    }

    private function validatedRecoveryCodeJson(string $value, TwoFactorService $twoFactor): string
    {
        try {
            $codes = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new JsonException('Recovery-code storage is malformed.', previous: $exception);
        }

        if (! is_array($codes)) {
            throw new JsonException('Recovery-code storage is malformed.');
        }

        $hashes = array_map(function (mixed $code) use ($twoFactor): string {
            if (! is_string($code) || $code === '') {
                throw new JsonException('Recovery-code storage is malformed.');
            }

            return preg_match('/^[a-f0-9]{64}$/i', $code)
                ? strtolower($code)
                : $twoFactor->hashRecoveryCode($code);
        }, $codes);

        return json_encode(array_values($hashes), JSON_THROW_ON_ERROR);
    }
}
