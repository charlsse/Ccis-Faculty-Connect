# Bounded local monitor: never sends or resends a webhook, never calls a provider.
param([string]$DeadlineUtc='')
$ErrorActionPreference='Stop'
$r='C:\Users\Charles\FacultyConnect-Sandbox\phase2-20261018'
$p=Join-Path $r 'runtime.env'
$env:FC_SANDBOX_CONFIG=$p
$evidence=Join-Path $r 'activation-20261018-212454'
$deadline=if($DeadlineUtc){[datetimeoffset]::Parse($DeadlineUtc).LocalDateTime}else{(Get-Date).AddMinutes(10)}
$reason='bounded_wait_expired'
try {
    do {
        $raw=& C:\xampp\php\php.exe C:\xampp\htdocs\CCIS_CONNECT\tools\sandbox\single-trial-status.php
        if($LASTEXITCODE -ne 0){$reason='status_check_failed';break}
        $status=$raw | ConvertFrom-Json
        if($status.attempt_status -eq 'confirmed'){
            $reason=if($status.attempt_count -eq 1 -and $status.submission_count -eq 1 -and $status.receipt_count -eq 1 -and $status.submission_method -eq 'xendit_test'){'settlement_verified'}else{'settlement_count_mismatch'}
            break
        }
        $log=Join-Path $r 'recovery-webhook-metadata.jsonl'
        if(Test-Path $log){$last=Get-Content -LiteralPath $log -Tail 1 | ConvertFrom-Json; if($last.http_status -ge 400){$reason='webhook_rejected';break}}
        Start-Sleep -Seconds 5
    } while((Get-Date) -lt $deadline)
} catch {$reason='monitor_error'}
finally {
    $s=[IO.File]::ReadAllText($p)
    foreach($key in @('PAYMENT_GATEWAY_ENABLED','TUNNEL_ENABLED','FC_SANDBOX_REMOTE_APPROVED')){$s=[regex]::Replace($s,"(?m)^$key=[^\r\n]*",($key+'="false"'))}
    [IO.File]::WriteAllText($p,$s,(New-Object Text.UTF8Encoding($false)))
    $rec=Get-Content "$evidence\process.json" -Raw | ConvertFrom-Json
    $proc=Get-Process -Id $rec.pid -ErrorAction SilentlyContinue
    if($proc -and $proc.ProcessName -eq 'ngrok'){Stop-Process -Id $proc.Id}
    @{reason=$reason;stopped_at=(Get-Date).ToString('o');flags_disabled=$true} | ConvertTo-Json | Set-Content "$evidence\recovery-stop.json"
    & C:\xampp\php\php.exe C:\xampp\htdocs\CCIS_CONNECT\tools\sandbox\preservation-check.php $r | Set-Content "$evidence\recovery-preservation.json"
}
