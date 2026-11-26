<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try{
    require __DIR__.'/bootstrap.php';$root=dirname(getenv('FC_SANDBOX_CONFIG'));
    $fixture=json_decode(file_get_contents($root.'/fixtures.json'),true);
    $member=App\Models\WaterMember::findOrFail($fixture['future_trial']);
    $attempts=App\Models\WaterGatewayAttempt::where('water_member_id',$member->id)->get();
    $a=$attempts->first();$receipt=$member->receipt()->first();
    $result=['attempt_count'=>$attempts->count(),'attempt_status'=>$a?->status,'issue_code'=>$a?->issue_code,'member_status'=>$member->status,
        'submission_count'=>$member->submissions()->count(),'receipt_count'=>$member->receipt()->count(),
        'provider_payment_identity_present'=>filled($a?->provider_payment_id),'confirmation_timestamp_present'=>filled($a?->confirmed_at),
        'submission_method'=>$a?->submission?->payment_method,'remote_approval'=>env('FC_SANDBOX_REMOTE_APPROVED',false)];
    file_put_contents($root.'/single-trial-status.json',json_encode($result,JSON_PRETTY_PRINT));echo json_encode($result).PHP_EOL;
}catch(Throwable$e){fwrite(STDERR,"Trial status unavailable; no secret details emitted.\n");exit(1);}
