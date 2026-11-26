<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'facultyconnect:backup-database {--label=manual : Safe filename label}';

    protected $description = 'Create a private MySQL backup without exposing database credentials';

    public function handle(): int
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}");

        if ($connection !== 'mysql' || ! is_array($database)) {
            $this->error('The FacultyConnect backup command currently supports MySQL only.');

            return self::INVALID;
        }

        $binary = (string) config('data-protection.mysqldump_binary');
        $label = preg_replace('/[^a-z0-9_-]+/i', '-', (string) $this->option('label')) ?: 'manual';
        $directory = storage_path('app/private/backups');
        $path = $directory.'/facultyconnect-'.$label.'-'.now()->format('Ymd-His').'.sql';

        File::ensureDirectoryExists($directory, 0700);

        $process = new Process([
            $binary,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--events',
            '--default-character-set=utf8mb4',
            '--host='.(string) $database['host'],
            '--port='.(string) $database['port'],
            '--user='.(string) $database['username'],
            '--result-file='.$path,
            (string) $database['database'],
        ], env: ['MYSQL_PWD' => (string) ($database['password'] ?? '')]);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful() || ! File::exists($path) || File::size($path) === 0) {
            if (File::exists($path)) {
                File::delete($path);
            }

            $this->error('Database backup failed. Credentials and database output were not displayed.');

            return self::FAILURE;
        }

        @chmod($path, 0600);
        $this->info('Database backup completed.');
        $this->line('Private file: '.$path);
        $this->line('SHA-256: '.hash_file('sha256', $path));

        return self::SUCCESS;
    }
}
