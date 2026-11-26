$ErrorActionPreference = 'Stop'
$project = 'C:\xampp\htdocs\CCIS_CONNECT'
$evidence = Join-Path $project ('storage\app\private\demo-activation-'+(Get-Date -Format 'yyyyMMdd-HHmmss'))
$agent = $null
try {
    if (Get-Process ngrok -ErrorAction SilentlyContinue) { throw 'Existing ngrok process requires review.' }
    New-Item -ItemType Directory -Path $evidence | Out-Null
    & (Join-Path $project 'tools\sandbox\Stage-NgrokEnvironment.ps1') | Out-Null
    $agent = Start-Process -FilePath 'C:\Users\Charles\AppData\Local\Microsoft\WindowsApps\ngrok.exe' -ArgumentList @('start','facultyconnect-main-demo','--config',(Join-Path $project 'tools\demo-ngrok.example.yml'),'--log=stdout','--log-format=json') -WindowStyle Hidden -PassThru -RedirectStandardOutput "$evidence\agent.private.out" -RedirectStandardError "$evidence\agent.private.err"
    Remove-Item Env:NGROK_AUTHTOKEN -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 3
    $agent.Refresh()
    if ($agent.HasExited) {
        $logs = [IO.File]::ReadAllText("$evidence\agent.private.out")+[IO.File]::ReadAllText("$evidence\agent.private.err")
        $codes = @([regex]::Matches($logs,'ERR_NGROK_[0-9]+') | ForEach-Object {$_.Value} | Select-Object -Unique)
        Write-Output ($codes -join ', ')
        throw 'ngrok exited; no retry or broader exposure attempted.'
    }
    $base = 'https://skid-porridge-snoring.ngrok-free.dev'
    $results = @()
    foreach ($path in @('/','/login','/dashboard','/.env','/up','/css/facultyconnect.css','/webhooks/payments/xendit','/webhooks/payments/xendit-demo','/payments/xendit/demo-return/not-a-uuid')) {
        $status = & 'C:\xampp\apache\bin\curl.exe' --silent --show-error --max-time 12 --header 'ngrok-skip-browser-warning: 1' --output NUL --write-out '%{http_code}' ($base+$path)
        $results += @{method='GET';path=$path;status=$status}
        $results | ConvertTo-Json | Set-Content "$evidence\denied-routes.json"
        if ($LASTEXITCODE -ne 0 -or $status -ne '404') { throw 'Public denied-route verification failed.' }
    }
    foreach ($path in @('/login','/payments/xendit/demo-return/11111111-1111-4111-8111-111111111111')) {
        $status = & 'C:\xampp\apache\bin\curl.exe' --silent --show-error --max-time 12 --request POST --header 'ngrok-skip-browser-warning: 1' --output NUL --write-out '%{http_code}' ($base+$path)
        $results += @{method='POST';path=$path;status=$status}
        $results | ConvertTo-Json | Set-Content "$evidence\denied-routes.json"
        if ($LASTEXITCODE -ne 0 -or $status -ne '404') { throw 'Public denied-method verification failed.' }
    }
    # Same stateless return fixture as check-demo-ingress.cjs. This endpoint
    # deliberately does not load a ledger row or claim payment success.
    $ref = '11111111-1111-4111-8111-111111111111'
    $returnOrigin = & 'C:\Program Files\nodejs\node.exe' -e "process.stdout.write(require('./tools/presentation-origin.cjs')())"
    if ($LASTEXITCODE -ne 0) { throw 'Unapproved return origin.' }
    $headers = & 'C:\xampp\apache\bin\curl.exe' --silent --show-error --max-time 12 --header 'ngrok-skip-browser-warning: 1' --dump-header - --output "$evidence\return-body.private.txt" ($base+'/payments/xendit/demo-return/'+$ref)
    $returnExit = $LASTEXITCODE
    # Retain only safe response metadata, never cookies or arbitrary header values.
    $headerText = $headers -join "`n"
    @{curl_exit=$returnExit;status_codes=@([regex]::Matches($headerText,'HTTP/\S+ ([0-9]{3})') | ForEach-Object {$_.Groups[1].Value});expected_location_present=($headerText -match [regex]::Escape($returnOrigin+'/dashboard/water/demo/'+$ref));location_header_present=($headerText -match '(?im)^location:')} | ConvertTo-Json | Set-Content "$evidence\return-check.json"
    if ($returnExit -ne 0 -or $headerText -notmatch 'HTTP/\S+ 303' -or $headerText -notmatch [regex]::Escape($returnOrigin+'/dashboard/water/demo/'+$ref)) { throw 'Public return destination verification failed.' }
    $status = & 'C:\xampp\apache\bin\curl.exe' --silent --show-error --max-time 12 --request POST --header 'ngrok-skip-browser-warning: 1' --header 'Content-Type: application/json' --data '{}' --output NUL --write-out '%{http_code}' ($base+'/webhooks/payments/xendit-demo')
    if ($LASTEXITCODE -ne 0 -or $status -notin @('401','503')) { throw 'Unauthenticated callback protection verification failed.' }
    $results | ConvertTo-Json | Set-Content "$evidence\denied-routes.json"
    @{pid=$agent.Id;started=(Get-Date).ToString('o');exposure_verified=$true;upstream='http://127.0.0.1:8005';return_verified=$true;disabled_callback_status=$status} | ConvertTo-Json | Set-Content "$evidence\process.json"
    Write-Output "Restricted exposure verified. ngrok PID $($agent.Id). Evidence: $evidence"
} catch {
    if ($agent -and !$agent.HasExited) { Stop-Process -Id $agent.Id }
    Remove-Item Env:NGROK_AUTHTOKEN -ErrorAction SilentlyContinue
    if (Test-Path $evidence) { @{stopped=$true;reason=$_.Exception.Message} | ConvertTo-Json | Set-Content "$evidence\stopped.json" }
    Write-Output ('STOPPED: '+$_.Exception.Message)
    exit 1
}
