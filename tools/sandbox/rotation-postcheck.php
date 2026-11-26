<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
try {
    require __DIR__.'/bootstrap.php';
    $root = dirname(getenv('FC_SANDBOX_CONFIG'));
    $dirs = glob($root.'/rotation-*', GLOB_ONLYDIR); sort($dirs); $evidence=end($dirs);
    if (!$evidence || !is_file($evidence.'/result.json')) throw new RuntimeException();
    $old = Dotenv\Dotenv::parse(file_get_contents($evidence.'/before-runtime.env'));
    $now = Dotenv\Dotenv::parse(file_get_contents($root.'/runtime.env'));
    $checks=[];
    foreach (['APP_KEY','DB_PASSWORD','FC_LOCAL_TLS_TOKEN'] as $key) $checks[$key.'_changed']=!hash_equals($old[$key],$now[$key]);
    $unchanged=true; foreach ($old as $key=>$value) if (!in_array($key,['APP_KEY','DB_PASSWORD','FC_LOCAL_TLS_TOKEN','NGROK_AUTHTOKEN'],true) && ($now[$key]??null)!==$value) $unchanged=false;
    $checks['other_runtime_settings_unchanged']=$unchanged;
    $c=curl_init('http://127.0.0.1:8002/login');
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['Host: localhost:8443','X-FC-Sandbox-Local-TLS: '.$old['FC_LOCAL_TLS_TOKEN']]]);
    curl_exec($c);$checks['old_bridge_token_rejected']=curl_getinfo($c,CURLINFO_HTTP_CODE)===404;curl_close($c);
    $db=Illuminate\Support\Facades\DB::connection();
    $checks['all_test_sessions_logged_out']=$db->table('sessions')->whereNotNull('user_id')->count()===0;
    $fixtures=json_decode(file_get_contents($root.'/fixtures.json'),true);
    $m=$db->table('water_members')->where('id',$fixtures['future_trial'])->first();
    $checks['future_trial_unused']=$m->status==='unpaid' && !$db->table('water_gateway_attempts')->where('water_member_id',$m->id)->exists();
    try { $db->select('SELECT id FROM ccis_local_20261018_164245.users LIMIT 1'); $checks['normal_database_access_denied']=false; }
    catch (Illuminate\Database\QueryException $e) { $checks['normal_database_access_denied']=in_array($e->errorInfo[1]??0,[1044,1142],true); }
    $checks['ngrok_token_present']=!empty($now['NGROK_AUTHTOKEN']);
    // A syntax sanity check only, not provider authentication or revocation verification.
    $checks['ngrok_token_format_plausible']=(bool)preg_match('/^[A-Za-z0-9_\-]{20,256}$/D',$now['NGROK_AUTHTOKEN']??'');
    $checks['ngrok_token_changed_since_rotation']=!hash_equals($old['NGROK_AUTHTOKEN']??'', $now['NGROK_AUTHTOKEN']??'');
    file_put_contents($evidence.'/postcheck.json',json_encode($checks,JSON_PRETTY_PRINT));
    echo json_encode($checks).PHP_EOL;
} catch (Throwable $e) { fwrite(STDERR,"Postcheck failed; no secret details emitted.\n");exit(1); }
