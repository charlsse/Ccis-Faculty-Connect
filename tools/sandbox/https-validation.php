<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/packages/autoload.php';
$root=dirname(getenv('FC_SANDBOX_CONFIG'));$checks=[];
function check($ok,$name){global$checks;$checks[$name]=(bool)$ok;if(!$ok)throw new RuntimeException($name);}
function requestLocal($method,$path,$data=[],$jar='guest'){
    global$root;$c=curl_init('https://localhost:8443'.$path);$headers=[];
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>15,CURLOPT_CAINFO=>$root.'/localhost.crt',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_RESOLVE=>['localhost:8443:127.0.0.1'],CURLOPT_COOKIEJAR=>$root.'/'.$jar.'.cookies',CURLOPT_COOKIEFILE=>$root.'/'.$jar.'.cookies',CURLOPT_HEADERFUNCTION=>function($c,$line)use(&$headers){$headers[]=$line;return strlen($line);}]);
    if($method==='POST')curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data)]);
    $body=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);$ssl=curl_getinfo($c,CURLINFO_SSL_VERIFYRESULT);curl_close($c);return['body'=>$body,'status'=>$status,'headers'=>$headers,'ssl'=>$ssl];
}
function csrf($body){preg_match('/name="_token" value="([^"]+)"/',$body,$m);return html_entity_decode($m[1]??'');}
try{
    $r=requestLocal('GET','/login');check($r['ssl']===0&&$r['status']===200,'TLS certificate and hostname verified');
    $cookies=array_filter($r['headers'],fn($h)=>str_starts_with(strtolower($h),'set-cookie: facultyconnect_xendit_test_'));
    check(count($cookies)>0,'Separate synthetic session cookie');foreach($cookies as$c)check(str_contains(strtolower($c),'secure')&&str_contains(strtolower($c),'httponly')&&str_contains(strtolower($c),'samesite=lax'),'Secure HttpOnly SameSite cookie');
    $accounts=json_decode(file_get_contents($root.'/synthetic-accounts.private.json'),true);
    foreach(['faculty','admin','treasurer']as$role){$u=$accounts[$role];$r=requestLocal('GET','/login',[],$role);
        $r=requestLocal('POST','/login',['_token'=>csrf($r['body']),'login'=>$u['faculty_id'],'password'=>$u['password']],$role);check($r['status']===302,$role.' password login');
        $r=requestLocal('GET','/two-factor-challenge',[],$role);check($r['status']===200,$role.' TOTP challenge');
        $code=(new PragmaRX\Google2FA\Google2FA)->getCurrentOtp($u['totp_secret']);
        $r=requestLocal('POST','/two-factor-challenge',['_token'=>csrf($r['body']),'code'=>$code],$role);check($r['status']===302,$role.' TOTP accepted');
        foreach(['/dashboard','/dashboard/security','/dashboard/water']as$path){$r=requestLocal('GET',$path,[],$role);check($r['status']===200,$role.' '.$path);}
        $r=requestLocal('GET','/dashboard/water/reports',[],$role);check($r['status']===($role==='faculty'?403:200),$role.' report permission');
        $r=requestLocal('GET','/dashboard/water/accounts',[],$role);check($r['status']===($role==='treasurer'?200:403),$role.' Treasurer account permission');
        $r=requestLocal('GET','/dashboard',[],$role);$r=requestLocal('POST','/logout',['_token'=>csrf($r['body'])],$role);check($r['status']===302,$role.' logout');
        check(requestLocal('GET','/dashboard',[],$role)['status']===302,$role.' logout blocks dashboard');
    }
    file_put_contents($root.'/https-validation.json',json_encode(['checks'=>$checks,'total'=>count($checks),'certificate_validation_disabled'=>false],JSON_PRETTY_PRINT));
    echo json_encode(['https_checks'=>count($checks),'passed'=>true,'certificate_validation_disabled'=>false]).PHP_EOL;
}catch(Throwable$e){file_put_contents($root.'/https-validation-failed.json',json_encode(['checks'=>$checks,'exception'=>get_class($e)],JSON_PRETTY_PRINT));fwrite(STDERR,json_encode(['failed_check'=>array_key_last($checks),'exception'=>get_class($e)]).PHP_EOL);exit(1);}
