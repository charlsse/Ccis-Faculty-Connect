<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RestoreDatabase extends Command
{
    protected $signature = 'facultyconnect:restore-database
        {backup : SQL filename from storage/app/private/backups}
        {--force : Confirm replacement of the configured MySQL database}';

    protected $description = 'Restore a private FacultyConnect MySQL backup without exposing credentials';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Database restoration requires --force.');

            return self::INVALID;
        }

        $connection = config('database.default');
        $database = config("database.connections.{$connection}");

        if ($connection !== 'mysql' || ! is_array($database)) {
            $this->error('The FacultyConnect restore command currently supports MySQL only.');

            return self::INVALID;
        }

        $directory = realpath(storage_path('app/private/backups'));
        $candidate = realpath(storage_path('app/private/backups/'.basename((string) $this->argument('backup'))));

        if ($directory === false || $candidate === false
            || ! str_starts_with($candidate, $directory.DIRECTORY_SEPARATOR)
            || strtolower(pathinfo($candidate, PATHINFO_EXTENSION)) !== 'sql') {
            $this->error('The requested private SQL backup was not found.');

            return self::INVALID;
        }

        $input = fopen($candidate, 'rb');

        if ($input === false) {
            $this->error('The requested backup could not be opened.');

            return self::FAILURE;
        }

        $process = new Process([
            (string) config('data-protection.mysql_binary'),
            '--host='.(string) $database['host'],
            '--port='.(string) $database['port'],
            '--user='.(string) $database['username'],
            '--default-character-set=utf8mb4',
            (string) $database['database'],
        ], env: ['MYSQL_PWD' => (string) ($database['password'] ?? '')]);
        $process->setTimeout(300);
        $process->setInput($input);
        $process->run();
        fclose($input);

        if (! $process->isSuccessful()) {
            $this->error('Database restoration failed. Credentials and database output were not displayed.');

            return self::FAILURE;
        }

        $this->info('Database restoration completed successfully.');

        return self::SUCCESS;
    }
}
