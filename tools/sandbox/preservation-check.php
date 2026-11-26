<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try{
    $root=$argv[1]??'';$before=json_decode(file_get_contents($root.'/preservation-before.json'),true,512,JSON_THROW_ON_ERROR);
    $c=json_decode(file_get_contents('C:/Users/Charles/FacultyConnect-Recovery/20261012/isolated-credentials.json'),true,512,JSON_THROW_ON_ERROR);
    $p=new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4',$c['user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $result=[];
    foreach($before as$db=>$tables){$unchanged=true;foreach($tables as$t=>$old){$rows=$p->query('SELECT * FROM `'.$db.'`.`'.$t.'`')->fetchAll(PDO::FETCH_ASSOC);$hashes=array_map(fn($r)=>hash('sha256',json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)),$rows);sort($hashes);if(count($rows)!==$old['count']||hash('sha256',implode("\n",$hashes))!==$old['hash'])$unchanged=false;}$result[$db]=['tables_checked'=>count($tables),'unchanged'=>$unchanged];}
    $result['normal_env_unchanged']=hash_equals(trim(file_get_contents($root.'/normal-env.sha256')),hash_file('sha256',dirname(__DIR__,2).'/.env'));
    $result['server']=$p->query('SELECT VERSION() version, @@port port, @@innodb_force_recovery recovery')->fetch(PDO::FETCH_ASSOC);
    file_put_contents($root.'/preservation-after.json',json_encode($result,JSON_PRETTY_PRINT));echo json_encode($result).PHP_EOL;
}catch(Throwable$e){fwrite(STDERR,"Preservation verification failed.\n");exit(1);}
