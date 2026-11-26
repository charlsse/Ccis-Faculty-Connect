# Authorized trial prerequisite only: no checkout or provider API requests.
$ErrorActionPreference='Stop'
$r='C:\Users\Charles\FacultyConnect-Sandbox\phase2-20261018'
$envPath=Join-Path $r 'runtime.env'
$yamlPath=Join-Path $r 'ngrok.yml'
$evidence=Join-Path $r ('activation-'+(Get-Date -Format 'yyyyMMdd-HHmmss'))
$ngrok=$null
$utf=New-Object Text.UTF8Encoding($false)
function Set-Flags([string]$gateway,[string]$tunnel) {
    $text=[IO.File]::ReadAllText($envPath)
    foreach($entry in @(@('PAYMENT_GATEWAY_ENABLED',$gateway),@('TUNNEL_ENABLED',$tunnel),@('FC_SANDBOX_REMOTE_APPROVED','false'))) {
        if([regex]::Matches($text,"(?m)^$($entry[0])=[^\r\n]*").Count -ne 1){throw 'Missing unique activation setting'}
        $text=[regex]::Replace($text,"(?m)^$($entry[0])=[^\r\n]*",($entry[0]+'="'+$entry[1]+'"'))
    }
    [IO.File]::WriteAllText($envPath,$text,$utf)
}
try {
    if(Get-Process ngrok -ErrorAction SilentlyContinue){throw 'Existing ngrok process; stop for review'}
    $yaml=[IO.File]::ReadAllText($yamlPath)
    if(!$yaml.Contains('url: http://127.0.0.1:8002') -or $yaml.Contains('8000') -or $yaml -match 'authtoken:') {throw 'Unexpected upstream/configuration'}
    $expression="!((req.url.path == '/webhooks/payments/xendit' && req.method == 'POST') || (req.url.path == '/payments/xendit/return' && req.method == 'GET'))"
    foreach($required in @($expression,'web_addr: false','inspect_db_size: -1','remote_management: false','status_code: 404','url: https://skid-porridge-snoring.ngrok-free.dev')) {
        if(!$yaml.Contains($required)){throw 'Required restriction missing'}
    }
    New-Item -ItemType Directory -Path $evidence | Out-Null
    # Public denial probes run before any callback can reach settlement.
    Set-Flags 'false' 'false'
    & (Join-Path $PSScriptRoot 'Stage-NgrokEnvironment.ps1') | Out-Null
    $ngrok=Start-Process -FilePath 'C:\Users\Charles\AppData\Local\Microsoft\WindowsApps\ngrok.exe' -ArgumentList @('start','facultyconnect-synthetic','--config',$yamlPath,'--log=stdout','--log-format=json') -WindowStyle Hidden -PassThru -RedirectStandardOutput "$evidence\agent.private.out" -RedirectStandardError "$evidence\agent.private.err"
    Remove-Item Env:NGROK_AUTHTOKEN -ErrorAction SilentlyContinue
    @{pid=$ngrok.Id;started=(Get-Date).ToString('o');remote_provider_enabled=$false} | ConvertTo-Json | Set-Content "$evidence\process.json"
    Start-Sleep -Seconds 3
    $ngrok.Refresh()
    if($ngrok.HasExited){
        $logs=[IO.File]::ReadAllText("$evidence\agent.private.out")+[IO.File]::ReadAllText("$evidence\agent.private.err")
        $codes=@([regex]::Matches($logs,'ERR_NGROK_[0-9]+') | ForEach-Object {$_.Value} | Select-Object -Unique)
        @{agent_exited=$true;provider_error_codes=$codes} | ConvertTo-Json | Write-Output
        throw 'Agent exited; do not retry authentication'
    }
    $results=@()
    $base='https://skid-porridge-snoring.ngrok-free.dev'
    foreach($path in @('/','/login','/dashboard','/admin','/dashboard/security','/dashboard/water','/up','/.env','/storage/test.txt','/css/app.css','/webhooks/payments/xendit')) {
        $status=& 'C:\xampp\apache\bin\curl.exe' --silent --max-time 12 --header 'ngrok-skip-browser-warning: 1' --output NUL --write-out '%{http_code}' ($base+$path)
        $results+=@{method='GET';path=$path;status=$status}
        if($LASTEXITCODE -ne 0 -or $status -ne '404'){throw 'Denied route did not return 404; tunnel stopped'}
    }
    foreach($path in @('/login','/payments/xendit/return')) {
        $status=& 'C:\xampp\apache\bin\curl.exe' --silent --max-time 12 --request POST --header 'ngrok-skip-browser-warning: 1' --output NUL --write-out '%{http_code}' ($base+$path)
        $results+=@{method='POST';path=$path;status=$status}
        if($LASTEXITCODE -ne 0 -or $status -ne '404'){throw 'Denied POST did not return 404; tunnel stopped'}
    }
    $results | ConvertTo-Json | Set-Content "$evidence\disabled-route-results.json"
    Set-Flags 'true' 'true'
    $status=& 'C:\xampp\apache\bin\curl.exe' --silent --max-time 12 --request POST --header 'Content-Type: application/json' --header 'ngrok-skip-browser-warning: 1' --data '{}' --output NUL --write-out '%{http_code}' ($base+'/webhooks/payments/xendit')
    $results+=@{method='POST';path='/webhooks/payments/xendit';authentication='missing';status=$status}
    if($LASTEXITCODE -ne 0 -or $status -ne '401'){throw 'Webhook authentication check failed; tunnel stopped'}
    $status=& 'C:\xampp\apache\bin\curl.exe' --silent --max-time 12 --header 'ngrok-skip-browser-warning: 1' --output NUL --write-out '%{http_code}' ($base+'/payments/xendit/return')
    $results+=@{method='GET';path='/payments/xendit/return';status=$status}
    if($LASTEXITCODE -ne 0 -or $status -ne '200'){throw 'Return route failed; tunnel stopped'}
    $results | ConvertTo-Json | Set-Content "$evidence\route-results.json"
    @{restricted_tunnel_running=$true;checks=$results.Count;provider_requests_disabled=$true;evidence=$evidence} | ConvertTo-Json
} catch {
    if($ngrok -and !$ngrok.HasExited){Stop-Process -Id $ngrok.Id -ErrorAction SilentlyContinue}
    Remove-Item Env:NGROK_AUTHTOKEN -ErrorAction SilentlyContinue
    Set-Flags 'false' 'false'
    if(Test-Path $evidence){@{stopped=$true;flags_disabled=$true} | ConvertTo-Json | Set-Content "$evidence\stopped.json"}
    Write-Output 'Activation stopped; all three flags false. No payment creation attempted. Review private diagnostics without exposing their contents.'
    exit 1
}
