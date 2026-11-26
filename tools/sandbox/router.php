<?php

// Dedicated PHP loopback process only. Never use this as the normal app router.
define('LARAVEL_START',microtime(true));
try {
    $app=require __DIR__.'/bootstrap.php';
    $local=in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)
        && hash_equals((string)env('FC_LOCAL_TLS_TOKEN'),(string)($_SERVER['HTTP_X_FC_SANDBOX_LOCAL_TLS']??''))
        && strlen((string)env('FC_LOCAL_TLS_TOKEN'))>=32;
    unset($_SERVER['HTTP_X_FC_SANDBOX_LOCAL_TLS']);
    $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
    if($path==='/webhooks/payments/xendit' && env('FC_SANDBOX_RECOVERY_ONLY',false)===true){
        $metadataPath=dirname(getenv('FC_SANDBOX_CONFIG')).'/recovery-webhook-metadata.jsonl';
        register_shutdown_function(function()use($metadataPath){file_put_contents($metadataPath,json_encode(['utc'=>gmdate(DATE_ATOM),'http_status'=>http_response_code()])."\n",FILE_APPEND|LOCK_EX);});
    }
    if($local){
        if(($_SERVER['HTTP_HOST']??'')!=='localhost:8443'){http_response_code(400);exit;}
        foreach(['HTTP_FORWARDED','HTTP_X_FORWARDED_HOST','HTTP_X_FORWARDED_FOR','HTTP_X_FORWARDED_PROTO']as$key)unset($_SERVER[$key]);
        $_SERVER['HTTPS']='on';$_SERVER['SERVER_PORT']='8443';
        if(preg_match('#^/(css|js|images)/[a-zA-Z0-9_./-]+\.(css|js|svg|png|jpg|jpeg|webp|ico)$#D',$path)){
            $public=realpath(dirname(__DIR__,2).'/public');$file=realpath($public.$path);
            if($file&&str_starts_with(str_replace('\\','/',$file),str_replace('\\','/',$public).'/')&&is_file($file)){
                $mime=['css'=>'text/css','js'=>'application/javascript','svg'=>'image/svg+xml','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','ico'=>'image/x-icon'];
                header('Content-Type: '.$mime[pathinfo($file,PATHINFO_EXTENSION)]);readfile($file);exit;
            }
        }
    }else{
        $host=strtolower(explode(':',$_SERVER['HTTP_HOST']??'')[0]);
        if(!config('tunnel.enabled')||$host!==config('tunnel.host')
            ||!(($_SERVER['REQUEST_METHOD']==='POST'&&$path==='/webhooks/payments/xendit')||($_SERVER['REQUEST_METHOD']==='GET'&&$path==='/payments/xendit/return'))){http_response_code(404);exit;}
    }
    $app->handleRequest(Illuminate\Http\Request::capture());
}catch(Throwable $e){http_response_code(503);header('Content-Type: text/plain');echo 'Synthetic test environment unavailable.';}
