<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WaterMember;

class WaterMemberPolicy
{
    public function view(User $user, WaterMember $member): bool
    {
        return $user->hasPermission('payments.view') || ($member->user_id === $user->id && $member->collection->status !== 'draft');
    }

    public function submit(User $user, WaterMember $member): bool
    {
        return $member->user_id === $user->id && $member->collection->status === 'published';
    }
}
