<?php

namespace App\Auth;

use Illuminate\Auth\SessionGuard;

/** Require an interactive sign-in instead of restoring a remembered login. */
class NoRecallerSessionGuard extends SessionGuard
{
    protected function recaller()
    {
        return null;
    }
}
