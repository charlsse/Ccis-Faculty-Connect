<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use PDOException;
use Tests\TestCase;

class DatabaseAvailabilityTest extends TestCase
{
    private function failingRoute(int $driverCode): void
    {
        Route::get('/test-database-outage', function () use ($driverCode) {
            $previous = new PDOException('Database connection unavailable');
            $previous->errorInfo = ['HY000', $driverCode, 'Database connection unavailable'];
            throw new QueryException('mysql', 'select 1', [], $previous);
        });
    }

    public function test_connection_outage_has_a_safe_retry_page(): void
    {
        $this->failingRoute(2002);
        $this->get('/test-database-outage')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '60')
            ->assertSee('We’re reconnecting')
            ->assertDontSee('select 1');
    }

    public function test_json_connection_outage_returns_retry_response(): void
    {
        $this->failingRoute(2013);
        $this->getJson('/test-database-outage')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '60')
            ->assertJsonStructure(['message']);
    }

    public function test_other_database_errors_are_not_hidden_as_connection_outages(): void
    {
        $this->failingRoute(1064);
        $this->get('/test-database-outage')->assertStatus(500);
    }
}
