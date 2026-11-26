$ErrorActionPreference = 'Stop'
$project = 'C:\xampp\htdocs\CCIS_CONNECT'
$logRoot = Join-Path $project 'storage\app\private\presentation-tunnel'
$mutex = New-Object Threading.Mutex($false, 'Local\FacultyConnectPresentationSupport')
$locked = $false
function Test-LoopbackPort([int]$port) {
    $client = New-Object Net.Sockets.TcpClient
    try { $client.Connect('127.0.0.1', $port); return $true } catch { return $false } finally { $client.Dispose() }
}
try {
    $locked = $mutex.WaitOne(0)
    if (-not $locked) { exit 0 }
    if (-not (Test-Path $logRoot)) { New-Item -ItemType Directory -Path $logRoot | Out-Null }
    if ([IO.DriveInfo]::new('C:\').AvailableFreeSpace -lt 128MB) { throw 'Storage safety floor reached.' }
    # Existing recovered-database availability task owns port 3307. Never start port 3306.
    if (-not (Test-LoopbackPort 3307)) { throw 'Recovered database unavailable; its existing recovery task must start it.' }
    if (-not (Test-LoopbackPort 8000)) {
        Start-Process -FilePath 'C:\xampp\php\php.exe' -ArgumentList @('artisan','serve','--host=127.0.0.1','--port=8000','--no-reload') -WorkingDirectory $project -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logRoot 'app.out.log') -RedirectStandardError (Join-Path $logRoot 'app.err.log') | Out-Null
    }
    if (-not (Test-LoopbackPort 8005)) {
        Start-Process -FilePath 'C:\Program Files\nodejs\node.exe' -ArgumentList 'tools/demo-ingress.cjs' -WorkingDirectory $project -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logRoot 'relay.out.log') -RedirectStandardError (Join-Path $logRoot 'relay.err.log') | Out-Null
    }
    # Reuse the reviewed narrow tunnel starter. Never copy or print its credentials.
    if (-not (Get-Process ngrok -ErrorAction SilentlyContinue)) {
        Push-Location $project
        try { & (Join-Path $project 'tools\Start-MainDemoTunnel.ps1') | Out-Null; if ($LASTEXITCODE -ne 0) { throw 'Restricted payment tunnel unavailable.' } }
        finally { Pop-Location }
    }
    $status = @{checked_at=(Get-Date).ToString('o');database_loopback=(Test-LoopbackPort 3307);app_loopback=(Test-LoopbackPort 8000);relay_loopback=(Test-LoopbackPort 8005);ngrok_running=[bool](Get-Process ngrok -ErrorAction SilentlyContinue);cloudflare_running=[bool](Get-Process cloudflared -ErrorAction SilentlyContinue);temporary_hostname_restart_requires_review=$true}
    $status | ConvertTo-Json | Set-Content (Join-Path $logRoot 'support-status.json')
    # No Cloudflare creation/restart: a new temporary hostname must not bypass approval.
} catch {
    @{checked_at=(Get-Date).ToString('o');status='unavailable';reason='A required local support service or storage check failed.'} | ConvertTo-Json | Set-Content (Join-Path $logRoot 'support-status.json')
    exit 1
} finally {
    if ($locked) { $mutex.ReleaseMutex() }
    $mutex.Dispose()
}
