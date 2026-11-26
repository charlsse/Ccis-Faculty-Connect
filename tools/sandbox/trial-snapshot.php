<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
try {
    require __DIR__.'/bootstrap.php';
    $root=dirname(getenv('FC_SANDBOX_CONFIG'));
    $db=Illuminate\Support\Facades\DB::connection();
    if($db->getDatabaseName()!=='ccis_xendit_test_20261018_111928' || config('payment_gateway.enabled') || config('tunnel.enabled') || env('FC_SANDBOX_REMOTE_APPROVED',false)) throw new RuntimeException();
    $fixtures=json_decode(file_get_contents($root.'/fixtures.json'),true,512,JSON_THROW_ON_ERROR);
    $member=$db->table('water_members')->where('id',$fixtures['future_trial'])->first();
    $recovery=($argv[1]??'')==='recover-existing-7';
    if(!$member || $member->status!=='unpaid' || (string)$member->assigned_amount!=='10.00') throw new RuntimeException();
    $attempts=$db->table('water_gateway_attempts')->where('water_member_id',$member->id)->get();
    if(!$recovery && $attempts->count())throw new RuntimeException();
    if($recovery){
        $a=$attempts->first();
        $s=json_decode(file_get_contents($root.'/diagnosis-20261018-124443/response-1.private.json'),true);
        $pr=json_decode(file_get_contents($root.'/diagnosis-20261018-124443/response-2.private.json'),true);
        if($attempts->count()!==1||$a->id!==7||$a->status!=='pending'||$a->provider_session_id!==$s['payment_session_id']
            ||'FC-WATER-'.$a->reference!==$s['reference_id']||($s['latest_payment_request_id']??$s['payment_request_id'])!==$pr['payment_request_id']
            ||$db->table('water_submissions')->where('water_member_id',$member->id)->exists()
            ||$db->table('water_receipts')->where('water_member_id',$member->id)->exists())throw new RuntimeException();
    }
    $dir=$root.'/trial-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
    if(!mkdir($dir,0700))throw new RuntimeException();
    $snapshot=function()use($db){
        $out=[];$db->statement('SET TRANSACTION READ ONLY');$db->beginTransaction();
        foreach($db->select('SHOW TABLES')as$tableRow){$table=array_values((array)$tableRow)[0];
            $rows=$db->table($table)->get()->map(fn($r)=>hash('sha256',json_encode((array)$r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)))->all();sort($rows);
            $out[$table]=['count'=>count($rows),'hash'=>hash('sha256',implode("\n",$rows))];
        }$db->rollBack();ksort($out);return$out;
    };
    $before=$snapshot();
    $env=getenv();$env['MYSQL_PWD']=config('database.connections.mysql.password');
    $file=$dir.'/synthetic-before.sql';
    $process=proc_open(['C:/xampp/mysql/bin/mysqldump.exe','--no-defaults','--host=127.0.0.1','--port=3307',
        '--user='.config('database.connections.mysql.username'),'--single-transaction','--quick','--skip-lock-tables','--skip-triggers','--hex-blob','--default-character-set=utf8mb4',$db->getDatabaseName()],
        [0=>['pipe','r'],1=>['file',$file,'w'],2=>['pipe','w']],$pipes,null,$env);
    if(!is_resource($process))throw new RuntimeException();fclose($pipes[0]);$err=stream_get_contents($pipes[2]);fclose($pipes[2]);$exit=proc_close($process);
    $sql=file_get_contents($file);$after=$snapshot();
    preg_match_all('/^CREATE TABLE `([^`]+)`/m',$sql,$matches);$tables=$matches[1];sort($tables);
    $ok=$exit===0 && str_contains($sql,'-- Dump completed on') && $tables===array_keys($before) && $before===$after;
    $result=['database'=>$db->getDatabaseName(),'verified_export'=>$ok,'dump_exit'=>$exit,'tables'=>count($tables),'sha256'=>hash_file('sha256',$file),
        'source_stable_during_export'=>$before===$after,'future_trial_unused'=>!$recovery,'existing_attempt_7_verified'=>$recovery,'amount'=>'10.00','currency'=>'PHP','restore_tested'=>false,
        'includes'=>'Tables and rows; triggers, events and routines excluded from scoped snapshot'];
    file_put_contents($dir.'/table-baseline.json',json_encode($before,JSON_PRETTY_PRINT));file_put_contents($dir.'/snapshot-result.json',json_encode($result,JSON_PRETTY_PRINT));
    echo json_encode(['directory'=>$dir]+$result).PHP_EOL;if(!$ok)exit(1);
}catch(Throwable $e){fwrite(STDERR,"Synthetic snapshot verification failed; no secret details emitted.\n");exit(1);}
