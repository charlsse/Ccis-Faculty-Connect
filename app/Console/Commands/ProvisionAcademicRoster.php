<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AcademicRosterProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;

class ProvisionAcademicRoster extends Command
{
    protected $signature = 'facultyconnect:academic-roster
        {--manifest= : Reviewed roster JSON}
        {--apply : Requires separate approval for normal database account inserts}
        {--actor= : Existing Administrator user ID}
        {--report= : New credentials JSON file in an ACL-protected directory outside the website}';

    protected $description = 'Review the academic roster; provision only on explicit operator request with Administrator authentication.';

    public function handle(AcademicRosterProvisioner $provisioner): int
    {
        $file = $this->option('manifest') ?: storage_path('app/private/academic-roster-review.json');
        $manifest = json_decode(file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
        $db = config('database.connections.'.config('database.default'));
        if (config('database.default') !== 'mysql' || $db['host'] !== '127.0.0.1' || (int)$db['port'] !== 3307
            || $db['database'] !== 'ccis_local_20261018_164245') {
            $this->error('Normal provisioning requires the approved main database on loopback port 3307.');
            return self::FAILURE;
        }
        $identity = DB::selectOne('SELECT DATABASE() db, @@port port, @@innodb_force_recovery recovery');
        if ($identity->db !== $db['database'] || (int)$identity->port !== 3307 || (int)$identity->recovery !== 0) {
            throw new \RuntimeException('Database identity mismatch.');
        }
        $review = $provisioner->review($manifest);
        $this->table(['Roster entry', 'Decision', 'Existing user ID'], array_map(fn ($r) => [$r['name'], $r['outcome'], $r['existing_user_id']], $review));
        if (!$this->option('apply')) return self::SUCCESS;
        $actor = User::findOrFail($this->option('actor'));
        if ($actor->role !== User::ROLE_ADMIN || !Hash::check((string)$this->secret('Administrator password (hidden)'), $actor->password)) {
            $this->error('Administrator authentication failed.');
            return self::FAILURE;
        }
        if (!$this->confirm('Separate approval obtained: insert only missing roster accounts on the normal database?', false)) return self::FAILURE;
        $report = $this->option('report');
        $directory = $report ? realpath(dirname($report)) : false;
        $root = strtolower(str_replace('\\', '/', realpath(base_path())));
        $resolved = strtolower(str_replace('\\', '/', (string)$directory));
        if (!$directory || $resolved === $root || str_starts_with($resolved, $root.'/') || file_exists($report)) {
            throw new \RuntimeException('Use a new report file in a protected directory outside the website.');
        }
        $check = new Process(['powershell.exe', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', base_path('tools/Test-PrivateRosterDirectory.ps1'), '-Directory', $directory]);
        $check->mustRun();
        $reportCreated = false;
        try {
            $result = $provisioner->provision($manifest, $actor, function (array $credentials) use ($report, &$reportCreated): void {
                $stream = fopen($report, 'x');
                if (!$stream) throw new \RuntimeException('Cannot exclusively create credential report.');
                $reportCreated = true;
                try {
                    $json = json_encode(['purpose' => 'Academic demonstration; generated IDs are not official UMak faculty IDs.', 'credentials' => $credentials], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    if (fwrite($stream, $json) !== strlen($json) || !fflush($stream)) throw new \RuntimeException('Credential report write failed.');
                } finally { fclose($stream); }
            });
        } catch (\Throwable $exception) {
            // A report from a rolled-back transaction must never be distributed.
            if ($reportCreated && is_file($report)) unlink($report);
            throw $exception;
        }
        $this->info(json_encode($result, JSON_THROW_ON_ERROR));
        $this->info('New credentials stored locally at '.$report.'; no messages sent.');
        return self::SUCCESS;
    }
}
