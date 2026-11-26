param([Parameter(Mandatory=$true)][string]$PrivateDirectory)
$ErrorActionPreference='Stop'
$project=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$privatePath=(Resolve-Path -LiteralPath $PrivateDirectory).Path
foreach($port in @(8002,8443)) { if(Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue) { throw "Port $port is already occupied; no existing process was changed." } }
$env:FC_SANDBOX_CONFIG=Join-Path $privatePath 'runtime.env'
$stamp=Get-Date -Format 'yyyyMMdd-HHmmss'
$php=Start-Process -FilePath 'C:\xampp\php\php.exe' -ArgumentList @('-S','127.0.0.1:8002',(Join-Path $PSScriptRoot 'router.php')) -WorkingDirectory $project -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $privatePath "$stamp-php.out") -RedirectStandardError (Join-Path $privatePath "$stamp-php.err")
$node=Start-Process -FilePath 'C:\Program Files\nodejs\node.exe' -ArgumentList @("--env-file=$env:FC_SANDBOX_CONFIG",(Join-Path $PSScriptRoot 'local-https.cjs')) -WorkingDirectory $project -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $privatePath "$stamp-https.out") -RedirectStandardError (Join-Path $privatePath "$stamp-https.err")
@{php_pid=$php.Id;https_pid=$node.Id;created=(Get-Date).ToString('o');public_tunnel_started=$false} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $privatePath "$stamp-processes.json") -Encoding UTF8
Write-Output 'Local synthetic processes launched. Check their private logs; no ngrok process was started.'
