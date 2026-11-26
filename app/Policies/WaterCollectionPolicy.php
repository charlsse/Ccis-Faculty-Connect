<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WaterCollection;

class WaterCollectionPolicy
{
    public function manage(User $user): bool
    {
        return $user->isActive() && $user->hasPermission('payments.manage');
    }

    public function view(User $user, WaterCollection $collection): bool
    {
        return $this->oversight($user) || ($collection->status !== 'draft' && $collection->members()->where('user_id', $user->id)->exists());
    }

    public function oversight(User $user): bool
    {
        return $user->isActive() && $user->hasPermission('payments.view');
    }
}
