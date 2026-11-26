<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if (! $app->environment('testing') || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Tests require testing environment and SQLite :memory:.');
        }

        return $app;
    }

    public function actingAs(UserContract $user, $guard = null): static
    {
        parent::actingAs($user, $guard);
        $this->withSession(['security_version' => (int) ($user->security_version ?? 0)]);

        if (isset($user->two_factor_confirmed_at) && $user->two_factor_confirmed_at) {
            $this->withSession([
                'two_factor_user_id' => $user->getAuthIdentifier(),
                'security_version' => (int) ($user->security_version ?? 0),
            ]);
        }

        return $this;
    }
}
