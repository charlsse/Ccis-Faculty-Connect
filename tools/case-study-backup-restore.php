<?php
// Explicitly authorized, CLI-only isolated case-study restore. No secrets in output.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Manila');
require dirname(__DIR__).'/packages/autoload.php';
$phase = 'read_only_preflight';
$dir = null;
try {
    $root = 'C:/Users/Charles/FacultyConnect-Recovery';
    $main = 'ccis_local_20261018_164245';
    $target = 'RESTORED_CCIS_FCLTY_CNNCT';
    $user = 'fc_case_readonly';
    if (disk_free_space($root) < 1024*1024*1024) throw new RuntimeException('Less than 1 GiB free; stopped before writes');
    $secret = json_decode(file_get_contents($root.'/20261012/isolated-credentials.json'),true,8,JSON_THROW_ON_ERROR);
    $opts = [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_STRINGIFY_FETCHES=>true,PDO::MYSQL_ATTR_MULTI_STATEMENTS=>false];
    $admin = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4',$secret['user'],$secret['password'],$opts);
    unset($secret);
    $identity = $admin->query('SELECT @@port port, @@datadir data_directory, @@version version, @@innodb_force_recovery recovery_mode')->fetch(PDO::FETCH_ASSOC);
    if ((int)$identity['port']!==3307 || (int)$identity['recovery_mode']!==0 || strtolower(str_replace('\\','/',rtrim($identity['data_directory'],'/\\'))) !== strtolower($root.'/20261012/restored-data')) throw new RuntimeException('Wrong instance');
    if ($admin->query('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='.$admin->quote($target))->fetchColumn()!=='0') throw new RuntimeException('Target already exists; no overwrite permitted');
    if ($admin->query('SELECT COUNT(*) FROM mysql.user WHERE User='.$admin->quote($user))->fetchColumn()!=='0') throw new RuntimeException('Inspection account already exists; will not change existing credentials');
    $backup = Dotenv\Dotenv::parse(file_get_contents($root.'/backup.env'));
    if ($backup['DB_HOST']!=='127.0.0.1'||$backup['DB_PORT']!=='3307'||$backup['DB_DATABASE']!==$main) throw new RuntimeException('Backup identity mismatch');
    $source = new PDO('mysql:host=127.0.0.1;port=3307;dbname='.$main.';charset=utf8mb4',$backup['DB_USERNAME'],$backup['DB_PASSWORD'],$opts);
    $tables = $source->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    sort($tables, SORT_STRING);
    if (!$tables || $source->query('SELECT COUNT(*) FROM users')->fetchColumn()!=='46') throw new RuntimeException('Expected 46-user baseline not present');
    if ($admin->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$main' AND (TABLE_TYPE!='BASE TABLE' OR ENGINE!='InnoDB')")->fetchColumn()!=='0') throw new RuntimeException('Unsupported table type');
    foreach (['TRIGGERS'=>'TRIGGER_SCHEMA','ROUTINES'=>'ROUTINE_SCHEMA','EVENTS'=>'EVENT_SCHEMA'] as $kind=>$column) {
        if ($admin->query("SELECT COUNT(*) FROM information_schema.$kind WHERE $column='$main'")->fetchColumn()!=='0') throw new RuntimeException('Executable database objects require separate review');
    }
    $fingerprint = function(PDO $pdo,array $tables) {
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        $result=[];
        foreach($tables as $table) {
            if(!preg_match('/^[a-zA-Z0-9_]+$/D',$table)) throw new RuntimeException('Unsafe identifier');
            $rows=$pdo->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_NUM);
            $hashes=array_map(fn($row)=>hash('sha256',serialize($row)),$rows); sort($hashes,SORT_STRING);
            $ddl=$pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
            $ddl=preg_replace('/ AUTO_INCREMENT=\d+/','',$ddl);
            $result[$table]=['rows'=>count($rows),'data_sha256'=>hash('sha256',implode('',$hashes)),'structure_sha256'=>hash('sha256',$ddl)];
        }
        $pdo->exec('COMMIT'); return $result;
    };
    $before=$fingerprint($source,$tables);
    $protectedFiles=['.env','config/database.php','storage/app/private/presentation-origin.json'];
    $fileHashes=[]; foreach($protectedFiles as $f) $fileHashes[$f]=hash_file('sha256',dirname(__DIR__).'/'.$f);
    $appGrants=$admin->query("SHOW GRANTS FOR 'fc_local_1018'@'127.0.0.1'")->fetchAll(PDO::FETCH_COLUMN);
    $phase='backup';
    $dir=$root.'/case-study-'.date('Ymd-His');
    if(!mkdir($dir,0700)) throw new RuntimeException('Cannot create protected directory');
    $file=$dir.'/FacultyConnect-backup-'.date('Ymd-His').'.sql';
    $env=getenv(); $env['MYSQL_PWD']=$backup['DB_PASSWORD'];
    $cmd=['C:/xampp/mysql/bin/mysqldump.exe','--no-defaults','--host=127.0.0.1','--port=3307','--user='.$backup['DB_USERNAME'],'--default-character-set=utf8mb4','--single-transaction','--quick','--skip-lock-tables','--skip-add-locks','--skip-disable-keys','--skip-extended-insert','--hex-blob','--routines','--events','--triggers',$main];
    $proc=proc_open($cmd,[0=>['pipe','r'],1=>['file',$file,'w'],2=>['pipe','w']],$pipes,null,$env);
    unset($env,$backup); fclose($pipes[0]); $err=stream_get_contents($pipes[2]); fclose($pipes[2]); $exit=proc_close($proc);
    if($exit!==0||trim($err)!=='') throw new RuntimeException('Dump failed; retain incomplete file for diagnosis');
    unset($err); $sql=file_get_contents($file);
    if(strlen($sql)<1000||!str_contains($sql,'-- Dump completed on ')) throw new RuntimeException('Incomplete backup');
    // Parse SQL with quote/comment awareness. Only mysqldump session SETs and
    // unqualified table DDL/one-row INSERTs are admitted. No client commands.
    $statements=[]; $buffer=''; $quote=null; $length=strlen($sql);
    for($i=0;$i<$length;$i++) {
        $c=$sql[$i]; $n=$sql[$i+1]??'';
        if($quote!==null) { $buffer.=$c; if($c==='\\'&&$quote!== '`') {$buffer.= $sql[++$i]??'';continue;} if($c===$quote){if($n===$quote){$buffer.=$sql[++$i];}else{$quote=null;}} continue; }
        if($c==="'"||$c==='"'||$c==='`') {$quote=$c;$buffer.=$c;continue;}
        if(($c==='-'&&$n==='-'&&ctype_space($sql[$i+2]??' '))||$c==='#'){while($i<$length&&$sql[$i]!=="\n")$i++;$buffer.="\n";continue;}
        if($c==='/'&&$n==='*'){ $end=strpos($sql,'*/',$i+2);if($end===false)throw new RuntimeException('Unclosed SQL comment');$inside=substr($sql,$i+2,$end-$i-2);if(str_starts_with($inside,'!'))$buffer.=' '.preg_replace('/^!\d*\s*/','',$inside).' ';$i=$end+1;continue;}
        if($c===';'){if(trim($buffer)!=='')$statements[]=trim($buffer);$buffer='';}else $buffer.=$c;
    }
    if($quote!==null||trim($buffer)!=='')throw new RuntimeException('Incomplete SQL statement');
    $counts=array_fill_keys($tables,0);$created=[];
    foreach($statements as $stmt){
        if(preg_match('/^SET\s/i',$stmt)) {if(preg_match('/\b(GLOBAL|PERSIST|PASSWORD|ROLE|SQL_LOG_BIN)\b|\\\\|\./i',$stmt))throw new RuntimeException('Unsafe SET');continue;}
        if(preg_match('/^DROP TABLE IF EXISTS `([a-zA-Z0-9_]+)`$/D',$stmt,$m)){if(!array_key_exists($m[1],$counts))throw new RuntimeException('Unknown drop target');continue;}
        if(preg_match('/^CREATE TABLE `([a-zA-Z0-9_]+)`\s*\(/',$stmt,$m)){
            if(!array_key_exists($m[1],$counts)||isset($created[$m[1]])||preg_match('/`\s*\.\s*`|\b(DATA DIRECTORY|INDEX DIRECTORY|CONNECTION\s*=|SELECT|TRIGGER|EVENT|DEFINER)\b/i',$stmt))throw new RuntimeException('Unsafe table definition');$created[$m[1]]=true;continue;
        }
        if(preg_match('/^INSERT INTO `([a-zA-Z0-9_]+)` VALUES \(/',$stmt,$m)){if(!isset($created[$m[1]]))throw new RuntimeException('Unknown insert target');$counts[$m[1]]++;continue;}
        throw new RuntimeException('Unexpected SQL statement; import refused');
    }
    $exported=array_keys($created);sort($exported);if($exported!==$tables)throw new RuntimeException('Table coverage mismatch');
    $metadata=['file'=>$file,'bytes'=>filesize($file),'created_at'=>date(DATE_ATOM),'sha256'=>hash_file('sha256',$file),'dump_exit'=>0,'tables'=>count($tables),'backup_row_counts'=>$counts,'sql_target_review'=>'Only unqualified table DDL, INSERTs and session SET statements; no database selection/creation, routines, triggers or events'];
    file_put_contents($dir.'/backup-manifest.json',json_encode($metadata,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    $phase='isolated_restore';
    $admin->exec('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $password=bin2hex(random_bytes(24));$account="'".$user."'@'127.0.0.1'";
    $admin->exec('CREATE USER '.$account.' IDENTIFIED BY '.$admin->quote($password));
    $grantDb='`'.str_replace('_','\\_',$target).'`';
    $admin->exec('GRANT SELECT, INSERT, CREATE, DROP, REFERENCES ON '.$grantDb.'.* TO '.$account);
    file_put_contents($dir.'/heidisql-readonly-credentials.json',json_encode(['host'=>'127.0.0.1','port'=>3307,'database'=>$target,'username'=>$user,'password'=>$password],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    $restore=new PDO('mysql:host=127.0.0.1;port=3307;dbname='.$target.';charset=utf8mb4',$user,$password,$opts);unset($password);
    try {foreach($statements as $stmt)$restore->exec($stmt);}
    finally {$admin->exec('REVOKE ALL PRIVILEGES, GRANT OPTION FROM '.$account);$admin->exec('GRANT SELECT ON '.$grantDb.'.* TO '.$account);}
    $phase='verification';
    $restoredTables=$restore->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    sort($restoredTables, SORT_STRING);
    if($restoredTables!==$tables)throw new RuntimeException('Restored table set differs');
    $restored=$fingerprint($restore,$tables);$after=$fingerprint($source,$tables);
    $volatile=['sessions','cache','cache_locks','jobs','job_batches','failed_jobs'];$results=[];$stableOK=true;
    foreach($tables as $t){
        if($restored[$t]['rows']!==$counts[$t])throw new RuntimeException('Backup/restored row count mismatch');
        if($before[$t]['structure_sha256']!==$restored[$t]['structure_sha256']||$before[$t]['structure_sha256']!==$after[$t]['structure_sha256'])throw new RuntimeException('Structure mismatch');
        $same=$before[$t]===$restored[$t]&&$before[$t]===$after[$t];
        if(!in_array($t,$volatile,true)&&!$same)$stableOK=false;
        $results[$t]=['source_before'=>$before[$t]['rows'],'backup'=>$counts[$t],'restored'=>$restored[$t]['rows'],'source_after'=>$after[$t]['rows'],'structure_match'=>true,'content_match'=>$same,'transient'=>in_array($t,$volatile,true)];
        foreach($admin->query('CHECK TABLE `'.$target.'`.`'.$t.'` EXTENDED')->fetchAll(PDO::FETCH_ASSOC) as $check)if($check['Msg_type']==='error'||($check['Msg_type']==='status'&&$check['Msg_text']!=='OK'))throw new RuntimeException('Restored table check failed');
    }
    foreach($fileHashes as $f=>$hash)if(hash_file('sha256',dirname(__DIR__).'/'.$f)!==$hash)throw new RuntimeException('Protected configuration changed');
    if($appGrants!==$admin->query("SHOW GRANTS FOR 'fc_local_1018'@'127.0.0.1'")->fetchAll(PDO::FETCH_COLUMN))throw new RuntimeException('Application grants changed');
    $denied=false;try{$restore->query('SELECT COUNT(*) FROM `'.$main.'`.`users`');}catch(PDOException $e){$denied=true;}if(!$denied)throw new RuntimeException('Read-only user can access main database');
    $report=['completed_at'=>date(DATE_ATOM),'status'=>$stableOK?'PASS':'REVIEW_REQUIRED_CONCURRENT_SOURCE_CHANGES','identity'=>$identity,'main_database'=>$main,'restored_database'=>$target,'backup'=>$metadata,'all_backup_rows_restored'=>true,'all_structures_match'=>true,'stable_records_unchanged_and_match'=>$stableOK,'users_before'=>$before['users']['rows'],'users_restored'=>$restored['users']['rows'],'users_after'=>$after['users']['rows'],'application_grants_unchanged'=>true,'protected_configuration_unchanged'=>true,'readonly_account'=>$user,'main_access_denied'=>true,'results'=>$results,'limitations'=>'Website/background tasks remained active. Transient tables may change normally. Data equality proves only the observed snapshots; no source writes were issued by this procedure. SQL backup does not include uploaded files, APP_KEY, server accounts or separate demo ledgers. Restored schema is not connected to the website.'];
    file_put_contents($dir.'/verification-results.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    $csv=fopen($dir.'/table-verification.csv','w');fputcsv($csv,['Table','Source before','Backup','Restored','Source after','Structure match','Content match','Transient']);foreach($results as $t=>$r)fputcsv($csv,array_merge([$t],array_values($r)));fclose($csv);
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){
    // Never print raw driver errors, SQL, credentials, or row contents.
    $failure=['stopped'=>true,'phase'=>$phase,'evidence_directory'=>$dir,'reason'=>$e instanceof PDOException?'Database operation failed; inspect privately. No source write statements were issued.':$e->getMessage()];
    if($dir&&is_dir($dir))file_put_contents($dir.'/stopped.json',json_encode($failure,JSON_PRETTY_PRINT));
    fwrite(STDERR,json_encode($failure).PHP_EOL);exit(1);
}
