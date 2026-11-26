<?php
namespace App\Services\Payments;

use Illuminate\Support\Facades\DB;

// Shares the historical Session/Request/Payment capture verifier without invoking real settlement.
class DemoXenditGateway extends XenditGateway
{
    protected function allowUnusedExpiredRequest(): bool { return true; }

    public function assertReturnAvailable(): void
    {
        $reference = '11111111-1111-4111-8111-111111111111';
        try {
            $response = \Illuminate\Support\Facades\Http::withOptions(['allow_redirects' => false])
                ->withHeaders(['ngrok-skip-browser-warning' => '1'])
                ->connectTimeout(3)->timeout(8)
                ->get(rtrim($this->returnBase(), '/').'/payments/xendit/demo-return/'.$reference);
            $origin = config('presentation.origin') ?: 'http://127.0.0.1:8000';
            if ($response->status() === 303 && $response->header('Location') === $origin.'/dashboard/water/demo/'.$reference) return;
        } catch (\Throwable $e) {
            // A checkout must not be created with an unreachable return address.
        }
        throw new GatewayUnavailable('Online checkout is temporarily unavailable because the payment connection is offline. Please try again shortly.');
    }

    protected function credential(string $key): mixed
    {
        return config('xendit_demo.credentials.'.$key, app()->environment('testing') ? parent::credential($key) : null);
    }
    protected function returnBase(): string { return (string) config('xendit_demo.return_base'); }
    protected function attemptModel(): string { return \App\Models\XenditDemoAttempt::class; }

    protected function ignoreUnrelatedWebhook(array $body): bool
    {
        if (!in_array($body['event'] ?? null, ['payment_session.completed', 'payment_session.expired'], true)) return false;
        $data = $body['data'] ?? null;
        if (!is_array($data)) return false;
        // Require bounded routing identifiers, but do not apply settlement schema
        // to dashboard placeholders that cannot correlate to our ledger.
        $idFields = array_values(array_filter(['payment_session_id', 'id'], fn($field) => array_key_exists($field, $data)));
        if ($idFields === []) return false;
        foreach (array_merge($idFields, ['reference_id']) as $field) {
            if (!is_string($data[$field] ?? null) || $data[$field] === '' || strlen($data[$field]) > 255
                || preg_match('/[\x00-\x1f\x7f]/', $data[$field])) return false;
        }
        $this->assertDatabaseIdentity();
        $reference = str_starts_with($data['reference_id'], 'FC-WATER-') ? substr($data['reference_id'], 9) : null;
        // Dashboard Test and save uses data.id. Check every supplied identifier
        // for correlation; never let an alternate ID hide an existing attempt.
        $query = \App\Models\XenditDemoAttempt::whereIn('provider_session_id', array_map(fn($field) => $data[$field], $idFields));
        if ($reference !== null) $query->orWhere('reference', $reference);
        return !$query->exists();
    }

    public function available(): bool
    {
        $test = app()->environment('testing') && config('database.default') === 'sqlite' && config('database.connections.sqlite.database') === ':memory:';
        $main = config('database.default') === 'mysql'
            && config('database.connections.mysql.database') === config('xendit_demo.main_database')
            && config('database.connections.mysql.host') === '127.0.0.1'
            && (int) config('database.connections.mysql.port') === 3307;
        $ledger = config('database.connections.xendit_demo.driver') === 'sqlite'
            && ($test || config('database.connections.xendit_demo.database') === storage_path('app/private/xendit-demo.sqlite'));
        return (bool) (($test || $main) && $ledger && !app()->environment('production') && config('xendit_demo.enabled')
            && parse_url($this->returnBase(), PHP_URL_SCHEME) === 'https'
            && $this->credential('treasurer_account_confirmed')
            && preg_match('/^[a-f0-9]{24}$/D', (string) $this->credential('business_id'))
            && str_starts_with((string) $this->credential('secret_key'), 'xnd_development_')
            && strlen((string) $this->credential('callback_token')) >= 16);
    }

    protected function assertDatabaseIdentity(): void
    {
        if (app()->environment('testing')) return;
        $identity = DB::selectOne('SELECT DATABASE() db, @@port port, @@innodb_force_recovery recovery');
        if ($identity->db !== config('xendit_demo.main_database') || (int)$identity->port !== 3307 || (int)$identity->recovery !== 0) {
            throw new GatewayUnavailable('Main database identity mismatch.');
        }
    }
}
