<?php
if(PHP_SAPI!=='cli')exit;
try{
    $project=dirname(__DIR__,2);$mode=$argv[1]??'normal';
    if($mode==='wallet')require __DIR__.'/bootstrap.php';
    elseif($mode==='xendit')require dirname(__DIR__).'/sandbox/bootstrap.php';
    else{require $project.'/packages/autoload.php';$app=require $project.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();}
    $db=Illuminate\Support\Facades\DB::connection();$db->statement('SET TRANSACTION READ ONLY');$db->beginTransaction();
    $identity=$db->selectOne('SELECT DATABASE() db, @@port port, @@innodb_force_recovery recovery');
    $result=['mode'=>$mode,'identity'=>$identity,'users'=>$db->table('users')->count(),'receipts'=>$db->table('water_receipts')->count(),'wallet_tables_present'=>Illuminate\Support\Facades\Schema::hasTable('wallet_accounts'),'gateway_enabled'=>config('payment_gateway.enabled'),'wallet_simulation'=>config('wallet.enabled'),'debug'=>config('app.debug'),'guard'=>config('auth.guards.web.driver')];
    if($mode==='normal'){
        $result['overview_render_checks']=[];
        foreach(['treasurer','admin'] as $role){
            $user=App\Models\User::where('role',$role)->first();
            if(!$user){$result['overview_render_checks'][$role]='no existing account with this role';continue;}
            // In-memory render only, not a login, cookie issuance or authenticated HTTP request.
            Illuminate\Support\Facades\Auth::guard()->setUser($user);
            $request=Illuminate\Http\Request::create('/dashboard/treasury');$request->setUserResolver(fn()=>$user);
            $view=(new App\Http\Controllers\WalletController)->index($request,new App\Services\Wallet\WalletService);
            $data=$view->getData();$result['overview_render_checks'][$role]=['view'=>$view->name(),'receipt_count'=>$data['receipts']->total(),'data_queries_passed'=>true,'full_layout_rendered'=>false];
        }
        $result['normal_env_matches_cutover_baseline']=hash_equals(trim(file_get_contents('C:/Users/Charles/FacultyConnect-Sandbox/wallet-20261018/normal-env.sha256')),hash_file('sha256',$project.'/.env'));
    }
    $db->rollBack();echo json_encode($result).PHP_EOL;
}catch(Throwable $e){$q=$e;while($q->getPrevious())$q=$q->getPrevious();echo json_encode(['error_class'=>get_class($e),'source'=>basename($e->getFile()),'line'=>$e->getLine(),'sql_code'=>$q instanceof PDOException?($q->errorInfo[1]??null):null]).PHP_EOL;exit(1);}
