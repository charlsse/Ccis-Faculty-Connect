<?php

namespace App\Support;

use App\Models\TrustedDevice;
use Symfony\Component\HttpFoundation\Cookie;

class TrustedDeviceResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly ?TrustedDevice $device = null,
        public readonly ?Cookie $cookie = null,
        public readonly bool $forgetCookie = false,
    ) {}
}
