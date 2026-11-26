<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdministratorService
{
    public function bootstrap(array $data, #[\SensitiveParameter] ?string $password, bool $promote): User
    {
        return DB::transaction(function () use ($data, $password, $promote): User {
            DB::table('account_management_locks')->where('id', 1)->lockForUpdate()->first();
            if (User::where('role', User::ROLE_ADMIN)->exists()) {
                throw ValidationException::withMessages(['account' => 'An administrator already exists. Use the administrator recovery command.']);
            }
            $byEmail = User::where('email', $data['email'])->lockForUpdate()->first();
            $byId = User::where('faculty_id', $data['faculty_id'])->lockForUpdate()->first();
            if (($byEmail || $byId) && (! $byEmail || ! $byId || ! $byEmail->is($byId))) {
                throw ValidationException::withMessages(['account' => 'Email and employee ID do not identify the same account.']);
            }
            if ($byEmail) {
                if (! $promote || $byEmail->status === User::STATUS_DEACTIVATED) {
                    throw ValidationException::withMessages(['account' => 'Explicit promotion confirmation of a non-deactivated account is required.']);
                }
                $user = $byEmail;
                $user->forceFill(['role' => User::ROLE_ADMIN])->save();
                app(SessionManager::class)->invalidate($user);
                app(AuditLogger::class)->record('administrator.promoted_by_operator', $user);
            } else {
                if (! $password) {
                    throw ValidationException::withMessages(['account' => 'A hidden-input initial password is required.']);
                }
                $user = User::create([...$data, 'role' => User::ROLE_ADMIN, 'status' => User::STATUS_ACTIVE,
                    'password' => Hash::make($password)]);
                app(AuditLogger::class)->record('administrator.created_by_operator', $user);
            }

            return $user;
        });
    }

    public function update(User $member, array $data, User $actor): void
    {
        DB::transaction(function () use ($member, $data, $actor): void {
            DB::table('account_management_locks')->where('id', 1)->lockForUpdate()->first();
            $member->refresh();
            $losesAccess = ($data['role'] ?? $member->role) !== User::ROLE_ADMIN
                || ($data['status'] ?? $member->status) !== User::STATUS_ACTIVE;
            if ($member->isAdmin() && $member->isActive() && $losesAccess) {
                $otherUsable = User::where('role', User::ROLE_ADMIN)->where('status', User::STATUS_ACTIVE)
                    ->whereKeyNot($member->id)->where('must_change_password', false)
                    ->whereNotNull('email_verified_at')->whereNotNull('two_factor_confirmed_at')->exists();
                if (! $otherUsable) {
                    throw ValidationException::withMessages(['status' => 'The last usable administrator cannot be demoted or deactivated.']);
                }
            }
            $oldRole = $member->role;
            $oldStatus = $member->status;
            $member->update($data);
            $audit = app(AuditLogger::class);
            if ($oldRole !== $member->role) {
                $audit->record('account.role_changed', $member, $actor, ['from' => $oldRole, 'to' => $member->role]);
            }
            if ($oldStatus !== $member->status) {
                $audit->record($member->status === User::STATUS_DEACTIVATED ? 'account.deactivated' : 'account.status_changed',
                    $member, $actor, ['from' => $oldStatus, 'to' => $member->status]);
            }
            if ($oldRole !== $member->role || $oldStatus !== $member->status) {
                app(SessionManager::class)->invalidate($member);
            }
        });
    }
}
