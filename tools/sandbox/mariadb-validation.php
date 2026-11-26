<?php

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
use App\Models\{User,WaterCollection,WaterMember,WaterPaymentAccount,WaterGatewayAttempt,WaterReceipt};
use App\Services\Payments\WaterGatewayService;
use App\Services\WaterPaymentService;
use Illuminate\Support\Facades\{Artisan,DB,Http,Auth,Schema,Gate};

function ensure($condition,$name){if(!$condition)throw new RuntimeException($name);}
function fakeProvider(string $mode='COMPLETED'):void {
    Http::swap(new Illuminate\Http\Client\Factory);Http::preventStrayRequests();
    Http::fake(function($r)use($mode){
        if($r->method()==='POST'){
            usleep(250000);$id='ps-'.substr(hash('sha256',$r['reference_id']),0,24);
            file_put_contents(dirname(getenv('FC_SANDBOX_CONFIG')).'/provider-creations.jsonl',json_encode(['reference'=>$r['reference_id'],'id'=>$id])."\n",FILE_APPEND|LOCK_EX);
            return Http::response(['payment_session_id'=>$id,'reference_id'=>$r['reference_id'],'session_type'=>'PAY','mode'=>'PAYMENT_LINK','amount'=>$r['amount'],'country'=>'PH','currency'=>'PHP','status'=>'ACTIVE','business_id'=>str_repeat('a',24),'payment_link_url'=>'https://dev.xen.to/synthetic']);
        }
        if(str_contains($r->url(),'/sessions/')){
            $id=basename($r->url());$a=WaterGatewayAttempt::where('provider_session_id',$id)->firstOrFail();usleep(150000);
            return Http::response(['payment_session_id'=>$id,'reference_id'=>$a->display_reference,'session_type'=>'PAY','mode'=>'PAYMENT_LINK','amount'=>$a->amount_minor/100,'country'=>'PH','currency'=>'PHP','status'=>$mode,'business_id'=>str_repeat('a',24),'payment_request_id'=>'pr-'.$a->reference,'payment_id'=>'py-'.$a->reference]);
        }
        $reference=substr(basename($r->url()),3);$a=WaterGatewayAttempt::where('reference',$reference)->firstOrFail();
        return Http::response(['payment_request_id'=>'pr-'.$reference,'business_id'=>str_repeat('a',24),'country'=>'PH','currency'=>'PHP','request_amount'=>$a->amount_minor/100,'type'=>'PAY','capture_method'=>'AUTOMATIC','status'=>'SUCCEEDED','latest_payment_id'=>'py-'.$reference,'captures'=>[['status'=>'SUCCEEDED','capture_amount'=>$a->amount_minor/100,'capture_timestamp'=>gmdate(DATE_ATOM)]]]);
    });
}
try {
    $app=require __DIR__.'/bootstrap.php';$root=dirname(getenv('FC_SANDBOX_CONFIG'));$mode=$argv[1]??'verify';
    if($mode==='setup'){
        ensure(count(DB::select('SHOW TABLES'))===0,'Setup refuses a nonempty database');
        ensure(Artisan::call('migrate',['--path'=>'database/migrations','--database'=>'mysql','--force'=>true])===0,'Base migrations');
        ensure(Artisan::call('migrate',['--path'=>'database/experimental/xendit','--database'=>'mysql','--force'=>true])===0,'Gateway migration');
        $migration=require database_path('experimental/xendit/2026_10_18_180000_add_xendit_gateway_attempts.php');
        $migration->down();ensure(!Schema::hasTable('water_gateway_attempts'),'Empty rollback');$migration->up();
        $password=bin2hex(random_bytes(16));$accounts=[];
        foreach(['faculty','admin','treasurer']as$role){
            $u=new User;$u->forceFill(['faculty_id'=>'SYNTH-'.strtoupper($role),'first_name'=>'Synthetic','last_name'=>ucfirst($role),'email'=>$role.'@example.invalid','department'=>'Synthetic Lab','position'=>'Test only','contact_number'=>null,'role'=>$role,'status'=>'active','password'=>$password,'email_verified_at'=>now(),'activated_at'=>now(),'two_factor_secret'=>app(App\Services\TwoFactorService::class)->generateSecret(),'two_factor_recovery_codes'=>[],'two_factor_confirmed_at'=>now(),'security_version'=>0,'must_change_password'=>false]);$u->save();
            $accounts[$role]=['id'=>$u->id,'faculty_id'=>$u->faculty_id,'password'=>$password,'totp_secret'=>$u->two_factor_secret];
        }
        file_put_contents($root.'/synthetic-accounts.private.json',json_encode($accounts,JSON_PRETTY_PRINT));
        $account=WaterPaymentAccount::create(['label'=>'Synthetic Treasurer','account_name'=>'Synthetic Treasurer','masked_account_number'=>'TEST ONLY','qr_image_path'=>'synthetic.png','created_by'=>$accounts['treasurer']['id'],'is_active'=>true]);
        DB::table('water_settings')->where('id',1)->update(['active_account_id'=>$account->id]);
        $members=[];
        foreach(['double_create','duplicate_confirm','manual_race','manual_review','canceled','future_trial']as$scenario){
            $c=WaterCollection::create(['title'=>'Synthetic '.$scenario,'period'=>now()->format('Y-m'),'amount'=>'10.00','due_date'=>now()->addWeek(),'status'=>'published','published_at'=>now(),'payment_account_id'=>$account->id,'created_by'=>$accounts['treasurer']['id']]);
            $members[$scenario]=$c->members()->create(['user_id'=>$accounts['faculty']['id'],'assigned_amount'=>'10.00','assigned_at'=>now(),'status'=>'unpaid'])->id;
        }
        file_put_contents($root.'/fixtures.json',json_encode($members,JSON_PRETTY_PRINT));
        echo json_encode(['setup'=>'passed','tables'=>count(DB::select('SHOW TABLES')),'users'=>User::count(),'collections'=>WaterCollection::count(),'empty_gateway_rollback'=>'passed']).PHP_EOL;exit;
    }
    $members=json_decode(file_get_contents($root.'/fixtures.json'),true);
    config(['payment_gateway.enabled'=>true,'payment_gateway.xendit.secret_key'=>'xnd_development_synthetic_mock_only','payment_gateway.xendit.callback_token'=>'synthetic_mock_callback_token','payment_gateway.xendit.business_id'=>str_repeat('a',24),'payment_gateway.xendit.treasurer_account_confirmed'=>true]);
    fakeProvider();Auth::setUser(User::where('role','faculty')->firstOrFail());
    if($mode==='worker'){
        $operation=$argv[2];$scenario=$argv[3];$gate=$argv[4];
        file_put_contents($root.'/'.$gate.'.ready.'.getmypid(),'ready');$deadline=microtime(true)+15;
        while(!file_exists($root.'/'.$gate.'.go')){if(microtime(true)>$deadline)throw new RuntimeException('Barrier timeout');usleep(10000);}
        try{
            $member=WaterMember::findOrFail($members[$scenario]);
            if($operation==='create')app(WaterGatewayService::class)->initiate($member);
            elseif($operation==='confirm')app(WaterGatewayService::class)->reconcile(WaterGatewayAttempt::where('water_member_id',$member->id)->firstOrFail()->provider_session_id);
            elseif($operation==='cash'){Auth::setUser(User::where('role','treasurer')->firstOrFail());app(WaterPaymentService::class)->cash($member,['submitted_amount'=>'10.00','paid_at'=>now()]);}
            $result='completed';
        }catch(Illuminate\Validation\ValidationException $e){$result='safely_rejected';}
        echo json_encode(['operation'=>$operation,'scenario'=>$scenario,'result'=>$result]).PHP_EOL;exit;
    }
    if($mode==='prepare'){
        foreach(['duplicate_confirm','manual_race','canceled']as$s)app(WaterGatewayService::class)->initiate(WaterMember::findOrFail($members[$s]));
        fakeProvider('CANCELED');$a=WaterGatewayAttempt::where('water_member_id',$members['canceled'])->firstOrFail();app(WaterGatewayService::class)->reconcile($a->provider_session_id);
        ensure($a->fresh()->status==='pending' && $a->fresh()->active_member_id!==null,'Canceled session reservation');
        Auth::setUser(User::where('role','admin')->firstOrFail());ensure(Gate::allows('oversight',WaterCollection::class),'Admin can view');ensure(!Gate::allows('manage',WaterCollection::class),'Admin financial management denied');
        try{app(WaterPaymentService::class)->cash(WaterMember::findOrFail($members['future_trial']),['submitted_amount'=>'10.00','paid_at'=>now()]);throw new RuntimeException('Admin cash unexpectedly allowed');}catch(Illuminate\Auth\Access\AuthorizationException $e){}
        Auth::setUser(User::where('role','faculty')->firstOrFail());$member=WaterMember::findOrFail($members['manual_review']);
        app(WaterPaymentService::class)->submit($member,['submitted_amount'=>'10.00','reference_number'=>'SYNTHETIC-MANUAL-ONLY','paid_at'=>now(),'proof_path'=>'synthetic-test-proof.png']);
        Auth::setUser(User::where('role','treasurer')->firstOrFail());app(WaterPaymentService::class)->review($member->submissions()->firstOrFail(),['decision'=>'approve']);
        ensure($member->fresh()->status==='paid','Manual Treasurer approval');
        echo json_encode(['preparation'=>'passed','manual_workflow'=>'passed','admin_financial_write'=>'denied','canceled'=>'reserved']).PHP_EOL;exit;
    }
    $results=[];
    $probe=WaterGatewayAttempt::whereNotNull('active_member_id')->firstOrFail()->getAttributes();unset($probe['id']);
    $probe['reference']=(string)Illuminate\Support\Str::uuid();$probe['provider_session_id']=null;$probe['provider_payment_id']=null;
    try{DB::table('water_gateway_attempts')->insert($probe);throw new LogicException('Duplicate active member allowed');}catch(Illuminate\Database\QueryException $e){ensure(($e->errorInfo[1]??0)===1062,'Unique active reservation enforced');}
    $probe['active_member_id']=null;$probe['water_member_id']=999999999;
    try{DB::table('water_gateway_attempts')->insert($probe);throw new LogicException('Missing member allowed');}catch(Illuminate\Database\QueryException $e){ensure(($e->errorInfo[1]??0)===1452,'Foreign key enforced');}
    foreach(['double_create','duplicate_confirm','manual_race']as$s){$member=WaterMember::findOrFail($members[$s]);$attempts=WaterGatewayAttempt::where('water_member_id',$member->id)->get();ensure($attempts->count()===1,'Single attempt '.$s);
        if($s!=='double_create'){ensure($member->status==='paid','Paid '.$s);ensure($member->receipt()->count()===1,'Single receipt '.$s);ensure($member->submissions()->count()===1,'Single submission '.$s);}
        $results[$s]=['attempts'=>$attempts->count(),'receipts'=>$member->receipt()->count(),'status'=>$member->status];}
    $calls=array_map(fn($l)=>json_decode($l,true),file($root.'/provider-creations.jsonl',FILE_IGNORE_NEW_LINES));$references=array_column($calls,'reference');ensure(count($references)===count(array_unique($references)),'One provider creation per reference');
    $migration=require database_path('experimental/xendit/2026_10_18_180000_add_xendit_gateway_attempts.php');
    try{$migration->down();throw new LogicException('Rollback discarded history');}catch(RuntimeException $e){ensure(Schema::hasTable('water_gateway_attempts'),'Rollback guard retained history');}
    $tableChecks=[];$indexes=0;
    foreach(DB::select('SHOW TABLES')as$row){$t=array_values((array)$row)[0];$count=(int)DB::table($t)->count();foreach(DB::select('CHECK TABLE `'.$t.'` EXTENDED')as$c)ensure($c->Msg_type!=='error'&&($c->Msg_type!=='status'||$c->Msg_text==='OK'),'Table integrity '.$t);
        DB::table($t)->get();$names=array_unique(array_map(fn($i)=>$i->Key_name,DB::select('SHOW INDEX FROM `'.$t.'`')));
        foreach($names as$i){$scanned=DB::select('SELECT * FROM `'.$t.'` FORCE INDEX (`'.$i.'`)');ensure(count($scanned)===$count,'Index read '.$t);$indexes++;}$tableChecks[$t]=$count;}
    $constraints=DB::select("SELECT CONSTRAINT_NAME,TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION");$groups=[];
    foreach($constraints as$c)$groups[$c->TABLE_NAME.'.'.$c->CONSTRAINT_NAME][]=$c;
    foreach($groups as$group){$c=$group[0];$join=[];$nonnull=[];foreach($group as$col){$join[]='s.`'.$col->COLUMN_NAME.'`=p.`'.$col->REFERENCED_COLUMN_NAME.'`';$nonnull[]='s.`'.$col->COLUMN_NAME.'` IS NOT NULL';}
        $n=DB::selectOne('SELECT COUNT(*) n FROM `'.$c->TABLE_NAME.'` s LEFT JOIN `'.$c->REFERENCED_TABLE_NAME.'` p ON '.implode(' AND ',$join).' WHERE '.implode(' AND ',$nonnull).' AND p.`'.$c->REFERENCED_COLUMN_NAME.'` IS NULL')->n;ensure((int)$n===0,'Foreign key orphans');}
    foreach(['ccis_local_20261018_164245','ccis_connect_verified','ccis_candidate_20261018','ccis_connect']as$forbidden){try{DB::select('SELECT COUNT(*) FROM `'.$forbidden.'`.users');throw new LogicException('Scope escape');}catch(Illuminate\Database\QueryException $e){ensure(in_array($e->errorInfo[1]??0,[1044,1142],true),'Expected permission denial');}}
    try{DB::statement('CREATE TABLE forbidden_ddl_probe (id INT)');throw new LogicException('Runtime DDL allowed');}catch(Illuminate\Database\QueryException $e){ensure(($e->errorInfo[1]??0)===1142,'DDL permission denied');}
    ensure(DB::table('trusted_devices')->count()===0,'No imported trusted devices');ensure(User::where('email','not like','%@example.invalid')->count()===0,'Synthetic users only');
    $report=['database'=>DB::getDatabaseName(),'tables'=>$tableChecks,'extended_checks'=>'OK','index_scans'=>$indexes,'foreign_keys'=>count($groups),'orphans'=>0,'concurrent_results'=>$results,'provider_creations'=>count($calls),'rollback_with_history'=>'refused','runtime_scope'=>'verified','outbound_requests'=>'mocked only','verified_utc'=>gmdate(DATE_ATOM)];
    file_put_contents($root.'/mariadb-validation.json',json_encode($report,JSON_PRETTY_PRINT));echo json_encode($report).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,json_encode(['validation'=>'failed','exception'=>get_class($e),'source'=>basename($e->getFile()),'line'=>$e->getLine(),'check'=>$e->getFile()===__FILE__?$e->getMessage():'Private diagnostic required','stage'=>$mode??'bootstrap']).PHP_EOL);exit(1);}
