<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try{
    require __DIR__.'/bootstrap.php';
    $root=dirname(getenv('FC_SANDBOX_CONFIG'));
    if(config('payment_gateway.enabled')||config('tunnel.enabled')||env('FC_SANDBOX_REMOTE_APPROVED',false))throw new RuntimeException();
    $db=Illuminate\Support\Facades\DB::connection();
    if($db->getDatabaseName()!=='ccis_xendit_test_20261018_111928')throw new RuntimeException();
    $a=App\Models\WaterGatewayAttempt::findOrFail(7);
    $fixtures=json_decode(file_get_contents($root.'/fixtures.json'),true);
    if($a->water_member_id!==$fixtures['future_trial']||$a->amount_minor!==1000||$a->currency!=='PHP'||$a->provider!=='xendit'||!preg_match('/^ps-[a-f0-9]{24}$/D',$a->provider_session_id))throw new RuntimeException();
    $baseline=function()use($db){$out=[];foreach(['water_gateway_attempts','water_members','water_submissions','water_receipts','audit_logs']as$t)$out[$t]=hash('sha256',json_encode($db->table($t)->orderBy('id')->get()));return$out;};
    $before=$baseline();$dir=$root.'/diagnosis-'.gmdate('Ymd-His');mkdir($dir,0700);
    $metadata=[];
    Illuminate\Support\Facades\Event::listen(Illuminate\Http\Client\Events\RequestSending::class,function($e)use($a){
        if($e->request->method()!=='GET'||!preg_match('#^https://api\.xendit\.co/(sessions/'.preg_quote($a->provider_session_id,'#').'|v3/payment_requests/pr-[a-f0-9-]{36})$#D',$e->request->url()))throw new RuntimeException();
    });
    Illuminate\Support\Facades\Event::listen(Illuminate\Http\Client\Events\ResponseReceived::class,function($e)use(&$metadata,$dir){
        $b=$e->response->json();$n=count($metadata)+1;
        file_put_contents($dir.'/response-'.$n.'.private.json',$e->response->body());
        $metadata[]=['http_status'=>$e->response->status(),'status'=>is_array($b)&&in_array($b['status']??'', ['ACTIVE','COMPLETED','EXPIRED','CANCELED','SUCCEEDED','FAILED','PENDING','AUTHORIZED'],true)?$b['status']:null,
            'fields'=>is_array($b)?array_keys($b):[]];
    });
    // Only this CLI process enables adapter validation. Persistent runtime and public router stay disabled.
    config(['payment_gateway.enabled'=>true]);
    Illuminate\Support\Facades\Http::allowStrayRequests(['https://api.xendit.co/sessions/'.$a->provider_session_id,'https://api.xendit.co/v3/payment_requests/*']);
    $verified=false;$reason=null;$result=null;
    try{$result=app(App\Services\Payments\XenditGateway::class)->retrievePayment($a->provider_session_id);
        $verified=$result['status']==='succeeded'&&$result['reference']===$a->display_reference&&$result['amount_minor']===1000&&$result['currency']==='PHP';
    }catch(App\Services\Payments\GatewayUnavailable $e){$reason=$e->getMessage();}
    $out=['attempt_id'=>7,'provider_capture_verified'=>$verified,'adapter_result'=>$result['status']??null,'validation_failure'=>$reason,
        'requests'=>$metadata,'local_rows_unchanged'=>$before===$baseline(),'persistent_flags_changed'=>false,'reconciliation_performed'=>false,'diagnostics'=>$dir];
    file_put_contents($dir.'/summary.json',json_encode($out,JSON_PRETTY_PRINT));echo json_encode($out).PHP_EOL;
}catch(Throwable$e){fwrite(STDERR,"Read-only payment diagnosis stopped; no secret details emitted.\n");exit(1);}
