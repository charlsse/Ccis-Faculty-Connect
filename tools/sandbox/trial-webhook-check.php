<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try{
    require __DIR__.'/bootstrap.php';
    if(env('FC_SANDBOX_REMOTE_APPROVED',false))throw new RuntimeException();
    $root=dirname(getenv('FC_SANDBOX_CONFIG'));$db=Illuminate\Support\Facades\DB::connection();
    $baseline=function()use($db){$out=[];foreach(['water_members','water_gateway_attempts','water_submissions','water_receipts']as$t){$rows=$db->table($t)->orderBy('id')->get();$out[$t]=hash('sha256',json_encode($rows));}return$out;};
    $before=$baseline();$results=[];
    foreach(['wrong'=>'deliberately-invalid-token','valid'=>config('payment_gateway.xendit.callback_token')]as$kind=>$token){
        // Unknown event exercises authentication without identifying any payment or causing provider reads.
        $c=curl_init(config('app.url').'/webhooks/payments/xendit');
        curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['business_id'=>config('payment_gateway.xendit.business_id'),'event'=>'sandbox.authentication_probe']),
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','ngrok-skip-browser-warning: 1','x-callback-token: '.$token],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        curl_exec($c);$results[$kind.'_token_status']=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
    }
    $results['financial_rows_unchanged']=$before===$baseline();
    $results['passed']=$results['wrong_token_status']===401&&$results['valid_token_status']===200&&$results['financial_rows_unchanged'];
    $dirs=glob($root.'/activation-*',GLOB_ONLYDIR);sort($dirs);$dir=end($dirs);
    file_put_contents($dir.'/webhook-auth-results.json',json_encode($results,JSON_PRETTY_PRINT));echo json_encode($results).PHP_EOL;
    if(!$results['passed'])exit(1);
}catch(Throwable$e){fwrite(STDERR,"Webhook check failed; no secret details emitted.\n");exit(1);}
