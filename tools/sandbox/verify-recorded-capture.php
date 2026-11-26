<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try{
 require __DIR__.'/bootstrap.php';$root=dirname(getenv('FC_SANDBOX_CONFIG'));
 if(config('payment_gateway.enabled')||config('tunnel.enabled')||env('FC_SANDBOX_REMOTE_APPROVED',false))throw new RuntimeException();
 $dir=$root.'/diagnosis-20261018-124443';
 $s=json_decode(file_get_contents($dir.'/response-1.private.json'),true,512,JSON_THROW_ON_ERROR);
 $r=json_decode(file_get_contents($dir.'/response-2.private.json'),true,512,JSON_THROW_ON_ERROR);
 $p=json_decode(file_get_contents($dir.'/payment.private.json'),true,512,JSON_THROW_ON_ERROR);
 $a=App\Models\WaterGatewayAttempt::findOrFail(7);$before=$a->getRawOriginal();
 if($a->provider_session_id!==$s['payment_session_id'])throw new RuntimeException();
 Illuminate\Support\Facades\Http::swap(new Illuminate\Http\Client\Factory);
 Illuminate\Support\Facades\Http::preventStrayRequests();
 Illuminate\Support\Facades\Http::fake([
 'https://api.xendit.co/sessions/'.$s['payment_session_id']=>Illuminate\Support\Facades\Http::response($s),
 'https://api.xendit.co/v3/payment_requests/'.$r['payment_request_id']=>Illuminate\Support\Facades\Http::response($r),
 'https://api.xendit.co/v3/payments/'.$p['payment_id']=>Illuminate\Support\Facades\Http::response($p)]);
 config(['payment_gateway.enabled'=>true]); // Memory-only; no requests leave the mock client.
 $v=app(App\Services\Payments\XenditGateway::class)->retrievePayment($a->provider_session_id);
 $out=['recorded_capture_verified'=>$v['status']==='succeeded'&&$v['reference']===$a->display_reference&&$v['amount_minor']===1000&&$v['currency']==='PHP',
 'attempt_unchanged'=>$before===$a->fresh()->getRawOriginal(),'attempt_status'=>$a->fresh()->status,'real_provider_requests'=>0,'reconciliation_performed'=>false];
 file_put_contents($dir.'/corrected-offline-verification.json',json_encode($out,JSON_PRETTY_PRINT));echo json_encode($out).PHP_EOL;
}catch(Throwable$e){fwrite(STDERR,"Recorded response verification failed; no private details emitted.\n");exit(1);}
