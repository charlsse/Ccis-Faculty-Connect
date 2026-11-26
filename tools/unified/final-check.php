<?php
if(PHP_SAPI!=='cli')exit;
try{
    require __DIR__.'/bootstrap.php';$root=dirname(getenv('FC_UNIFIED_CONFIG'));$db=Illuminate\Support\Facades\DB::connection();$checks=[];$indexes=0;
    $db->statement('SET TRANSACTION READ ONLY');$db->beginTransaction();
    foreach($db->select('SHOW TABLES') as $row){$t=array_values((array)$row)[0];$count=$db->table($t)->count();foreach(array_unique(array_map(fn($i)=>$i->Key_name,$db->select('SHOW INDEX FROM `'.$t.'`'))) as $index){if(count($db->select('SELECT * FROM `'.$t.'` FORCE INDEX (`'.$index.'`)'))!==$count)throw new RuntimeException('Index scan mismatch.');$indexes++;}}
    $checks['no_provider_funds']=!$db->table('wallet_accounts')->exists()&&!$db->table('wallet_entries')->exists()&&!$db->table('wallet_withdrawals')->exists();
    $checks['one_quota_one_audit']=$db->table('wallet_targets')->count()===1&&$db->table('wallet_audits')->count()===1;
    $db->rollBack();
    try{$db->select('SELECT COUNT(*) FROM ccis_local_20261018_164245.users');$checks['scoped_copy_account']=false;}catch(Illuminate\Database\QueryException $e){$checks['scoped_copy_account']=in_array($e->errorInfo[1]??null,[1044,1142],true);}
    $creds=json_decode(file_get_contents('C:/Users/Charles/FacultyConnect-Recovery/20261012/isolated-credentials.json'),true);$p=new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4',$creds['user'],$creds['password']);$p->exec('SET TRANSACTION READ ONLY');$p->beginTransaction();
    $before=json_decode(file_get_contents($root.'/source-before.json'),true);$checks['normal_records_unchanged']=true;
    foreach($before as $table=>$expected){$rows=$p->query('SELECT * FROM `ccis_local_20261018_164245`.`'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);$hashes=array_map(fn($r)=>hash('sha256',json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)),$rows);sort($hashes);if(count($rows)!==$expected['count']||hash('sha256',implode("\n",$hashes))!==$expected['hash'])$checks['normal_records_unchanged']=false;}
    $checks['normal_wallet_schema_absent']=(int)$p->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='ccis_local_20261018_164245' AND TABLE_NAME='wallet_accounts'")->fetchColumn()===0;
    $roleStates=$p->query("SELECT role,status,COUNT(*) count FROM ccis_local_20261018_164245.users GROUP BY role,status")->fetchAll(PDO::FETCH_ASSOC);$p->rollBack();
    $checks['normal_env_unchanged']=hash_equals(trim(file_get_contents($root.'/normal-env.sha256')),hash_file('sha256',dirname(__DIR__,2).'/.env'));
    $result=['database'=>$db->getDatabaseName(),'indexes'=>$indexes,'checks'=>$checks,'role_state_counts'=>$roleStates,'all_passed'=>!in_array(false,$checks,true)];file_put_contents($root.'/final-check.json',json_encode($result,JSON_PRETTY_PRINT));echo json_encode($result).PHP_EOL;
}catch(Throwable $e){echo "Final verification failed; no private details emitted.\n";exit(1);}
