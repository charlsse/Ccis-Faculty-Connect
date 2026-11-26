<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SessionManager
{
    public function __construct(private readonly TrustedDeviceService $trustedDevices) {}

    public function invalidate(User $user, ?string $exceptSessionId = null): void
    {
        $this->trustedDevices->revokeAll($user, 'security_session_invalidation');
        $user->forceFill(['security_version' => $user->security_version + 1])->save();

        if (! Schema::hasTable(config('session.table', 'sessions'))) {
            return;
        }

        $query = DB::table(config('session.table', 'sessions'))->where('user_id', $user->id);

        if ($exceptSessionId) {
            $query->where('id', '!=', $exceptSessionId);
        }

        $query->delete();
    }
}
