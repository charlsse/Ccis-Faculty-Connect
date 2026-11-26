<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try{
 require __DIR__.'/bootstrap.php';$root=dirname(getenv('FC_SANDBOX_CONFIG'));
 $db=Illuminate\Support\Facades\DB::connection();$db->statement('SET TRANSACTION READ ONLY');$db->beginTransaction();
 $a=App\Models\WaterGatewayAttempt::findOrFail(7);$m=$a->member;$s=$a->submission;$r=$m->receipt()->first();
 $checks=[
 'synthetic_database'=>$db->getDatabaseName()==='ccis_xendit_test_20261018_111928',
 'confirmed_attempt'=>$a->status==='confirmed'&&$a->issue_code===null&&$a->confirmed_at!==null,
 'paid_member'=>$m->status==='paid','reservation_released'=>$a->active_member_id===null,
 'one_member_attempt'=>App\Models\WaterGatewayAttempt::where('water_member_id',$m->id)->count()===1,
 'unique_provider_payment'=>filled($a->provider_payment_id)&&App\Models\WaterGatewayAttempt::where('provider_payment_id',$a->provider_payment_id)->count()===1,
 'unique_session'=>filled($a->provider_session_id)&&App\Models\WaterGatewayAttempt::where('provider_session_id',$a->provider_session_id)->count()===1,
 'one_submission'=>$m->submissions()->count()===1,'one_receipt'=>$m->receipt()->count()===1,
 'submission_link'=>$s&&$s->water_member_id===$m->id&&$s->status==='approved',
 'receipt_link'=>$r&&$s&&$r->water_submission_id===$s->id&&$r->water_member_id===$m->id,
 'exact_amount'=>$a->amount_minor===1000&&$a->currency==='PHP'&&(string)$m->assigned_amount==='10.00'&&(string)$s?->submitted_amount==='10.00'&&(string)$r?->amount==='10.00',
 'test_receipt'=>$a->environment==='sandbox'&&$s?->payment_method==='xendit_test'&&$r?->payment_method==='xendit_test'&&($r?->snapshot['officer_name']??null)==='Automatic - Xendit TEST',
 'reference_links'=>$s?->reference_number===$a->display_reference&&$r?->payment_reference===$a->display_reference,
 'all_activation_disabled'=>!config('payment_gateway.enabled')&&!config('tunnel.enabled')&&!env('FC_SANDBOX_REMOTE_APPROVED',false),
 ];
 $db->rollBack();$out=['checks'=>$checks,'all_passed'=>!in_array(false,$checks,true),'financial_writes'=>0,'provider_requests'=>0];
 file_put_contents($root.'/activation-20261018-212454/final-settlement-verification.json',json_encode($out,JSON_PRETTY_PRINT));echo json_encode($out).PHP_EOL;
 if(!$out['all_passed'])exit(1);
}catch(Throwable$e){fwrite(STDERR,"Read-only settlement verification failed; no private details emitted.\n");exit(1);}
