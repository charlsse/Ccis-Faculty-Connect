<?php

// Verify only notification delivery/read state. No payment/receipt/account writes or provider requests.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/packages/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
ini_set('display_errors', '0');
$selected = null;
$originalReadAt = null;
try {
    $directory = $argv[1] ?? '';
    $deployment = json_decode(file_get_contents($directory.'/deployment-verified.json'), true, 32, JSON_THROW_ON_ERROR);
    if (!$deployment['deployed'] || config('water.email_enabled')) throw new RuntimeException('Deployment/email guard failed');
    $identity = Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() db, @@port port, @@innodb_force_recovery recovery');
    if ($identity->db !== 'ccis_local_20261018_164245' || (int)$identity->port !== 3307 || (int)$identity->recovery !== 0) throw new RuntimeException('Wrong main database');
    $notifications = app(App\Services\PaymentNotifications::class);
    $portal = app(App\Services\PortalNotifications::class);
    $source = Illuminate\Support\Facades\DB::connection('xendit_demo');
    $events = $source->table('notification_outbox')->orderBy('created_at')->get();
    if ($events->count() < 1 || $events->whereNull('delivered_at')->count()) throw new RuntimeException('Expected committed delivery evidence missing');
    $verifiedRecipients = 0;
    $confirmedEvents = 0;
    foreach ($events as $event) {
        $payload = json_decode($event->payload, true, 16, JSON_THROW_ON_ERROR);
        $payer = App\Models\User::findOrFail($payload['user_id']);
        $recipients = collect([$payer])->filter(fn ($user) => $user->isActive());
        if ($payload['status'] === 'confirmed') {
            $recipients = $recipients->concat($notifications->treasurers())->unique('id');
            $confirmedEvents++;
        }
        foreach ($recipients as $recipient) {
            $key = hash('sha256', $event->event_key.':'.$recipient->id);
            $notice = App\Models\WaterNotice::where('event_key', $key)->sole();
            if ($notice->user_id !== $recipient->id || !($notice->context['simulation'] ?? false)
                || $notice->email_state !== 'disabled' || $notice->context['route'] !== 'water.demo.show'
                || !str_contains($notice->message, 'No funds collected')) throw new RuntimeException('Recipient/classification mismatch');
            if (!$portal->entries($recipient)->contains('id', 'water-'.$notice->id)) throw new RuntimeException('Delivered notice absent from authorized in-app feed');
            $verifiedRecipients++;
            if ($recipient->id === $payer->id && !$selected) $selected = $notice;
        }
    }
    if (!$selected) throw new RuntimeException('No payer notification to verify');
    $owner = App\Models\User::findOrFail($selected->user_id);
    $originalReadAt = $selected->read_at;
    $entry = $portal->entries($owner)->firstWhere('id', 'water-'.$selected->id);
    // Only a newly deployed notification is touched; its original read state is restored afterward.
    $selected->update(['read_at' => null]);
    $other = App\Models\User::where('id', '!=', $owner->id)->where('status', 'active')->firstOrFail();
    $portal->markRead($other, collect([$entry]));
    if ($selected->fresh()->read_at !== null) throw new RuntimeException('Other user changed notification read state');
    $request = Illuminate\Http\Request::create('/dashboard/notifications/feed', 'GET');
    $request->setUserResolver(fn () => $owner);
    $controller = app(App\Http\Controllers\NotificationController::class);
    $beforeFeed = $controller->feed($request, $portal)->getData(true);
    $portal->markRead($owner, collect([$entry]));
    $afterFeed = $controller->feed($request, $portal)->getData(true);
    if ($selected->fresh()->read_at === null || $beforeFeed['unread'] !== $afterFeed['unread'] + 1) throw new RuntimeException('Owned unread count transition failed');
    $selected->update(['read_at' => $originalReadAt]);
    $selected = null;
    $countBefore = App\Models\WaterNotice::count();
    $keysBefore = App\Models\WaterNotice::whereNotNull('event_key')->orderBy('id')->pluck('event_key')->all();
    // Simulate only a lost source-delivery acknowledgment, never a financial/provider event.
    $replay = $events->first();
    $source->table('notification_outbox')->where('event_key', $replay->event_key)->update(['delivered_at' => null]);
    $replayed = $notifications->deliverDemo();
    if ($replayed !== 1 || App\Models\WaterNotice::count() !== $countBefore
        || App\Models\WaterNotice::whereNotNull('event_key')->orderBy('id')->pluck('event_key')->all() !== $keysBefore
        || $source->table('notification_outbox')->where('event_key', $replay->event_key)->value('delivered_at') === null) throw new RuntimeException('Durable replay deduplication failed');
    $pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
    foreach ($deployment['before'] as $table => $expected) {
        $rows = $pdo->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
        $encoded = array_map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR), $rows); sort($encoded, SORT_STRING);
        $actual = ['rows' => count($rows), 'sha256' => hash('sha256', json_encode($encoded, JSON_THROW_ON_ERROR))];
        if ($actual !== $expected) throw new RuntimeException('Protected financial/account fingerprint changed');
    }
    $demoBackup = new PDO('sqlite:'.$directory.'/demo-before.sqlite');
    if ($demoBackup->query('SELECT * FROM demo_attempts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)
        !== $source->getPdo()->query('SELECT * FROM demo_attempts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)) throw new RuntimeException('Existing demo payment records changed');
    $backupManifest = json_decode(file_get_contents($directory.'/backup-restoration-verified.json'), true, 32, JSON_THROW_ON_ERROR);
    $oldNotices = $backupManifest['stable_fingerprints']['water_notices'];
    $originalNotices = $pdo->query('SELECT id,user_id,water_collection_id,message,read_at,created_at,updated_at FROM water_notices ORDER BY id LIMIT '.(int)$oldNotices['rows'])->fetchAll(PDO::FETCH_ASSOC);
    $encodedOriginals = array_map(function ($row) {
        foreach ($row as &$value) if ($value !== null) $value = (string)$value;
        return json_encode($row, JSON_THROW_ON_ERROR);
    }, $originalNotices);
    sort($encodedOriginals, SORT_STRING);
    if (hash('sha256', json_encode($encodedOriginals, JSON_THROW_ON_ERROR)) !== $oldNotices['sha256']) throw new RuntimeException('Original notice contents or read states changed');
    $result = ['actual_in_app_delivery_verified' => true, 'source_events' => $events->count(), 'confirmed_events' => $confirmedEvents,
        'authorized_recipient_deliveries' => $verifiedRecipients, 'event_unique_notices' => count($keysBefore),
        'owner_read_unread_count_verified' => true, 'other_user_read_denied' => true, 'lost_ack_replay_deduplicated' => true,
        'original_read_state_restored' => true, 'protected_financial_tables_unchanged' => count($deployment['before']),
        'demo_payment_records_unchanged' => true, 'original_notices_and_read_states_unchanged' => true, 'active_treasurers' => $notifications->treasurers()->count(),
        'payer_and_treasurer_same_account_for_existing_confirmed_events' => App\Models\XenditDemoAttempt::where('status', 'confirmed')->get()->every(fn ($attempt) => $notifications->treasurers()->contains('id', $attempt->user_id)),
        'emails_sent' => 0, 'new_external_sessions' => 0, 'verification_level' => 'Installed database and application feed/controller; browser interaction not claimed'];
    file_put_contents($directory.'/live-delivery-verified.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($result).PHP_EOL;
} catch (Throwable $error) {
    if ($selected) $selected->update(['read_at' => $originalReadAt]);
    fwrite(STDERR, json_encode(['verification_failed' => true, 'exception_type' => get_class($error), 'reason' => $error instanceof Illuminate\Database\QueryException ? 'Notification verification database operation failed.' : $error->getMessage()]).PHP_EOL);
    exit(1);
}
