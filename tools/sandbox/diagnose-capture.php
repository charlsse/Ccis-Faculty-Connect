<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try{
 require __DIR__.'/bootstrap.php';$root=dirname(getenv('FC_SANDBOX_CONFIG'));
 if(config('payment_gateway.enabled')||config('tunnel.enabled')||env('FC_SANDBOX_REMOTE_APPROVED',false))throw new RuntimeException();
 $dir=$root.'/diagnosis-20261018-124443';$s=json_decode(file_get_contents($dir.'/response-1.private.json'),true,512,JSON_THROW_ON_ERROR);$pr=json_decode(file_get_contents($dir.'/response-2.private.json'),true,512,JSON_THROW_ON_ERROR);
 $a=App\Models\WaterGatewayAttempt::findOrFail(7);$id=$pr['latest_payment_id']??'';
 if(!preg_match('/^py-[a-f0-9-]{36}$/D',$id)||($s['payment_id']??null)!==$id||$s['payment_session_id']!==$a->provider_session_id||$s['reference_id']!==$a->display_reference)throw new RuntimeException();
 $url='https://api.xendit.co/v3/payments/'.$id;
 Illuminate\Support\Facades\Http::allowStrayRequests([$url]);
 $res=Illuminate\Support\Facades\Http::withBasicAuth(config('payment_gateway.xendit.secret_key'),'')->withHeaders(['api-version'=>'2024-11-11'])->acceptJson()->withOptions(['allow_redirects'=>false])->connectTimeout(3)->timeout(15)->get($url);
 file_put_contents($dir.'/payment.private.json',$res->body());$p=$res->json();if(!$res->successful()||!is_array($p))throw new RuntimeException();
 $checks=['payment_id_matches'=>($p['payment_id']??null)===$id,'payment_request_matches'=>($p['payment_request_id']??null)===$pr['payment_request_id'],
 'merchant_matches'=>($p['business_id']??null)===config('payment_gateway.xendit.business_id'),'currency_php'=>($p['currency']??null)==='PHP','country_ph'=>($p['country']??null)==='PH',
 'type_pay'=>($p['type']??null)==='PAY','automatic_capture'=>($p['capture_method']??null)==='AUTOMATIC','request_amount_matches'=>($p['request_amount']??null)==10,'payment_succeeded'=>($p['status']??null)==='SUCCEEDED'];
 $captures=$p['captures']??[];$sum=0;$valid=is_array($captures)&&count($captures)>0;$ids=[];
 foreach($captures as$c){$amount=$c['capture_amount']??null;$cap=$c['capture_id']??'';$stamp=$c['capture_timestamp']??'';
 $valid=$valid&&(is_int($amount)||is_float($amount))&&$amount>0&&abs($amount*100-round($amount*100))<0.00001&&is_string($cap)&&preg_match('/^cap-[a-f0-9-]{36}$/D',$cap)&&!in_array($cap,$ids,true)&&is_string($stamp)&&strtotime($stamp)!==false;
 $ids[]=$cap;if(is_numeric($amount))$sum+=(int)round($amount*100);
 }
 $checks['valid_unique_captures']=(bool)$valid;$checks['captured_amount_matches']=$sum===1000;
 $out=['http_status'=>$res->status(),'checks'=>$checks,'capture_count'=>count($captures),'captured_minor'=>$sum,'capture_evidence_verified'=>!in_array(false,$checks,true),'capture_entry_fields'=>array_map(fn($c)=>array_keys($c),$captures),'local_settlement_performed'=>false];
 file_put_contents($dir.'/capture-summary.json',json_encode($out,JSON_PRETTY_PRINT));echo json_encode($out).PHP_EOL;
}catch(Throwable$e){fwrite(STDERR,"Capture diagnosis failed; no secret details emitted.\n");exit(1);}
