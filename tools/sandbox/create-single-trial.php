<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try{
    require __DIR__.'/bootstrap.php';
    $root=dirname(getenv('FC_SANDBOX_CONFIG'));
    if(config('database.connections.mysql.database')!=='ccis_xendit_test_20261018_111928'||env('FC_SANDBOX_REMOTE_APPROVED',false)!==true)throw new RuntimeException();
    $fixtures=json_decode(file_get_contents($root.'/fixtures.json'),true,512,JSON_THROW_ON_ERROR);
    $member=App\Models\WaterMember::findOrFail($fixtures['future_trial']);
    $user=App\Models\User::findOrFail($member->user_id);
    if($user->faculty_id!=='SYNTH-FACULTY'||$user->email!=='faculty@example.invalid'||$member->status!=='unpaid'||(string)$member->assigned_amount!=='10.00'
        ||App\Models\WaterGatewayAttempt::where('water_member_id',$member->id)->exists())throw new RuntimeException();
    $lock=fopen($root.'/single-trial-creation.lock','x');if(!$lock)throw new RuntimeException();
    fwrite($lock,'Single creation authorized. Never remove/retry blindly.');fclose($lock);
    $metadata=[];
    Illuminate\Support\Facades\Event::listen(Illuminate\Http\Client\Events\ResponseReceived::class,function($event)use($root,&$metadata){
        $body=$event->response->json();
        $metadata[]=['http_status'=>$event->response->status(),'json_object'=>is_array($body),
            'error_code'=>is_array($body)&&is_string($body['error_code']??null)&&preg_match('/^[A-Z0-9_]{1,100}$/D',$body['error_code'])?$body['error_code']:null,
            'response_fields'=>is_array($body)?array_keys($body):[]];
        // Response body retained privately for contract diagnosis; no request headers or credentials.
        file_put_contents($root.'/single-trial-provider-response.private.json',$event->response->body());
    });
    Illuminate\Support\Facades\Auth::setUser($user);
    $attempt=app(App\Services\Payments\WaterGatewayService::class)->initiate($member);
    $result=['attempt_id'=>$attempt->id,'status'=>$attempt->status,'issue_code'=>$attempt->issue_code,'amount_minor'=>$attempt->amount_minor,
        'currency'=>$attempt->currency,'provider_response_metadata'=>$metadata,'checkout_ready'=>$attempt->status==='pending'&&App\Http\Controllers\WaterGatewayController::safeCheckout($attempt),
        'submission_count'=>$member->submissions()->count(),'receipt_count'=>$member->receipt()->count()];
    if($result['checkout_ready'])file_put_contents($root.'/single-trial-checkout.private.txt',$attempt->checkout_url);
    file_put_contents($root.'/single-trial-result.json',json_encode($result,JSON_PRETTY_PRINT));echo json_encode($result).PHP_EOL;
    if(!$result['checkout_ready'])exit(2);
}catch(Throwable$e){fwrite(STDERR,"Single trial creation stopped. Preserve any existing attempt and lock; do not retry.\n");exit(1);}
