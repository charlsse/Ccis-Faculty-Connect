<?php

// Used only by the explicit synthetic CLI/router, never normal public/index.php.
$project = dirname(__DIR__, 2);
$private = getenv('FC_SANDBOX_CONFIG');
if (! is_string($private) || ! is_file($private)) throw new RuntimeException('Private sandbox configuration required.');
$root = dirname(realpath($private));
if (str_starts_with(strtolower(str_replace('\\', '/', $root)), strtolower(str_replace('\\', '/', $project)))) throw new RuntimeException('Sandbox configuration must be outside the project.');
require_once $project.'/packages/autoload.php';
foreach (['APP_CONFIG_CACHE'=>'config.php','APP_ROUTES_CACHE'=>'routes.php','APP_SERVICES_CACHE'=>'services.php','APP_PACKAGES_CACHE'=>'packages.php'] as $key=>$name) {
    putenv($key.'='.$root.'/cache/'.$name); $_ENV[$key]=$_SERVER[$key]=$root.'/cache/'.$name;
}
$app = require $project.'/bootstrap/app.php';
$app->addAbsoluteCachePathPrefix('C:');
$app->useEnvironmentPath($root)->loadEnvironmentFrom(basename($private));
$app->useStoragePath($root.'/storage');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = config('database.connections.mysql.database');
if (config('database.default') !== 'mysql' || ! preg_match('/^ccis_xendit_test_[0-9_]+$/D', (string) $db)
    || config('database.connections.mysql.host') !== '127.0.0.1' || (int) config('database.connections.mysql.port') !== 3307
    || config('app.debug') !== false || config('payment_gateway.xendit.test_database') !== $db) throw new RuntimeException('Sandbox configuration identity rejected.');
$identity = Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() db, @@port port, @@innodb_force_recovery recovery');
if ($identity->db !== $db || (int)$identity->port !== 3307 || (int)$identity->recovery !== 0) throw new RuntimeException('Sandbox server identity rejected.');
config(['mail.default'=>'array','water.email_enabled'=>false,'queue.default'=>'sync',
    'cache.default'=>'file','cache.stores.file.path'=>$root.'/storage/framework/cache/data',
    'view.compiled'=>$root.'/storage/framework/views','session.files'=>$root.'/storage/framework/sessions',
    'filesystems.disks.local.root'=>$root.'/storage/app/private','filesystems.disks.public.root'=>$root.'/storage/app/public',
    'logging.default'=>'single','logging.channels.single.path'=>$root.'/storage/logs/laravel.log']);
// Remains false until the user separately approves real provider Test Mode requests.
Illuminate\Support\Facades\Http::preventStrayRequests();
if (env('FC_SANDBOX_REMOTE_APPROVED', false) === true) {
    Illuminate\Support\Facades\Http::allowStrayRequests([
        'https://api.xendit.co/sessions',
        'https://api.xendit.co/sessions/*',
        'https://api.xendit.co/v3/payment_requests/*',
        'https://api.xendit.co/v3/payments/*',
    ]);
    if (env('FC_SANDBOX_RECOVERY_ONLY', false) === true) {
        $savedSession=json_decode(file_get_contents($root.'/diagnosis-20261018-124443/response-1.private.json'),true,512,JSON_THROW_ON_ERROR);
        $savedRequest=json_decode(file_get_contents($root.'/diagnosis-20261018-124443/response-2.private.json'),true,512,JSON_THROW_ON_ERROR);
        $allowed=[
            'https://api.xendit.co/sessions/'.$savedSession['payment_session_id'],
            'https://api.xendit.co/v3/payment_requests/'.$savedRequest['payment_request_id'],
            'https://api.xendit.co/v3/payments/'.$savedRequest['latest_payment_id'],
        ];
        Illuminate\Support\Facades\Event::listen(Illuminate\Http\Client\Events\RequestSending::class,function($event)use($allowed){
            if($event->request->method()!=='GET'||!in_array($event->request->url(),$allowed,true))throw new RuntimeException('Recovery allows existing payment GETs only.');
        });
        Illuminate\Support\Facades\Event::listen(Illuminate\Http\Client\Events\ResponseReceived::class,function($event)use($root,$allowed){
            $body=$event->response->json();$status=is_array($body)?($body['status']??null):null;
            if(!in_array($status,['COMPLETED','ACTIVE','EXPIRED','CANCELED','SUCCEEDED','FAILED','AUTHORIZED','PENDING'],true))$status=null;
            file_put_contents($root.'/recovery-provider-metadata.jsonl',json_encode(['utc'=>gmdate(DATE_ATOM),'resource_index'=>array_search($event->request->url(),$allowed,true),'http_status'=>$event->response->status(),'provider_status'=>$status])."\n",FILE_APPEND|LOCK_EX);
        });
    }
}
return $app;
