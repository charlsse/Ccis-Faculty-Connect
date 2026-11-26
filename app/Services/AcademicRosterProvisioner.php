<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Operator-only service; no HTTP endpoint or self-service exception. */
class AcademicRosterProvisioner
{
    public function validateManifest(array $manifest): array
    {
        $records = $manifest['records'] ?? [];
        if (($manifest['source_sheet'] ?? '') !== 'FACULTY ROSTER' || count($records) !== 41
            || !preg_match('/^[a-f0-9]{64}$/D', $manifest['source_sha256'] ?? '')) {
            throw new \RuntimeException('Expected the reviewed 41-person teaching roster.');
        }
        $names = [];
        foreach ($records as $i => $row) {
            if (($row['roster_key'] ?? '') !== sprintf('CCIS-AY2627-%03d', $i + 1)
                || !in_array($row['employment_classification'] ?? '', ['FULL-TIME', 'PART-TIME'], true)) {
                throw new \RuntimeException('Invalid roster identifier or classification.');
            }
            foreach (['first_name', 'last_name', 'department', 'position', 'source_name'] as $field) {
                if (!is_string($row[$field] ?? null) || trim($row[$field]) === '' || mb_strlen($row[$field]) > 180) {
                    throw new \RuntimeException('Incomplete roster row.');
                }
            }
            $name = $this->normalize($row['first_name'].' '.$row['last_name']);
            if (isset($names[$name])) throw new \RuntimeException('Duplicate source name requires manual review.');
            $names[$name] = true;
        }
        if (count(array_filter($records, fn ($r) => $r['employment_classification'] === 'FULL-TIME')) !== 30) {
            throw new \RuntimeException('Teaching classifications do not reconcile.');
        }
        return $records;
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($value)));
    }

    public function review(array $manifest): array
    {
        $users = User::all();
        $result = [];
        $seen = [];
        foreach ($this->validateManifest($manifest) as $row) {
            $login = str_replace('CCIS-AY2627-', 'CCIS-DEMO-2627-', $row['roster_key']);
            $matches = $users->filter(fn ($u) => $this->normalize($u->first_name.' '.$u->last_name)
                === $this->normalize($row['first_name'].' '.$row['last_name']));
            $byId = $users->firstWhere('faculty_id', $login);
            $marker = Schema::hasTable('academic_roster_accounts')
                ? DB::table('academic_roster_accounts')->where('roster_key', $row['roster_key'])->first() : null;
            $match = $matches->count() === 1 ? $matches->first() : null;
            $conflict = $matches->count() > 1 || ($byId && (!$match || !$byId->is($match)))
                || ($marker && (!$match || (int) $marker->user_id !== (int) $match->id))
                || ($match && isset($seen[$match->id]));
            if ($match && $match->middle_name && ($row['middle_name'] ?? null)
                && substr($this->normalize($match->middle_name), 0, 1) !== substr($this->normalize($row['middle_name']), 0, 1)) {
                $conflict = true;
            }
            if ($match) $seen[$match->id] = true;
            $result[] = ['roster_key' => $row['roster_key'], 'name' => $row['source_name'],
                'outcome' => $conflict ? 'conflict' : ($match ? 'preserve_existing' : 'create'),
                'existing_user_id' => $match?->id, 'generated_login' => $match ? null : $login];
        }
        return $result;
    }

    /** $saveCredentials receives secrets in memory; it must write only to a protected local file. */
    public function provision(array $manifest, User $actor, callable $saveCredentials): array
    {
        $actor->refresh();
        if ($actor->role !== User::ROLE_ADMIN || $actor->status !== User::STATUS_ACTIVE
            || !$actor->email_verified_at || !$actor->two_factor_confirmed_at || $actor->must_change_password) {
            throw ValidationException::withMessages(['administrator' => 'An active, fully onboarded Administrator must provision the roster.']);
        }
        return DB::transaction(function () use ($manifest, $actor, $saveCredentials): array {
            // Serialize with normal administrator operations and re-reconcile under the lock.
            DB::table('account_management_locks')->where('id', 1)->lockForUpdate()->first();
            $review = $this->review($manifest);
            if (collect($review)->contains('outcome', 'conflict')) throw new \RuntimeException('Resolve roster conflicts before import.');
            $credentials = [];
            foreach ($review as $i => $decision) {
                if ($decision['outcome'] !== 'create') continue;
                $row = $manifest['records'][$i];
                $temporary = Str::password(28);
                $user = new User;
                $user->forceFill([
                    'faculty_id' => $decision['generated_login'], 'first_name' => $row['first_name'],
                    'middle_name' => $row['middle_name'] ?? null, 'last_name' => $row['last_name'],
                    'department' => $row['department'], 'position' => $row['position'], 'contact_number' => null,
                    'email' => null, 'email_verified_at' => null, 'role' => User::ROLE_FACULTY,
                    // Active directory membership; password replacement and TOTP still gate access.
                    'status' => User::STATUS_ACTIVE, 'password' => Hash::make($temporary),
                    'must_change_password' => true, 'temporary_password_expires_at' => now()->addHours(config('account-security.temporary_password_hours', 48)),
                    'security_version' => 0,
                ])->save();
                DB::table('academic_roster_accounts')->insert([
                    'roster_key' => $row['roster_key'], 'user_id' => $user->id, 'provisioned_by' => $actor->id,
                    'source_name' => $row['source_name'], 'employment_classification' => $row['employment_classification'],
                    'source_sha256' => $manifest['source_sha256'], 'created_at' => now(),
                ]);
                app(AuditLogger::class)->record('academic_roster.provisioned', $user, $actor,
                    ['role' => User::ROLE_FACULTY, 'email_optional' => true, 'totp_required' => true]);
                $credentials[] = ['name' => $row['source_name'], 'login_id' => $user->faculty_id,
                    'temporary_password' => $temporary, 'role' => $user->role, 'status' => $user->status,
                    'expires_at' => $user->temporary_password_expires_at->toIso8601String()];
            }
            if ($credentials !== []) $saveCredentials($credentials);
            return ['roster_total' => count($review), 'matched_existing' => count($review) - count($credentials),
                'created' => count($credentials), 'active_users' => User::where('status', User::STATUS_ACTIVE)->count()];
        });
    }
}
