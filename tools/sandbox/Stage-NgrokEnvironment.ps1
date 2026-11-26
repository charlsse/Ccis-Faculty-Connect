# Stages authentication only for this PowerShell process and future children.
# Does not authenticate remotely, write ngrok configuration, or start a tunnel.
$ErrorActionPreference = 'Stop'
$privateFile = 'C:\Users\Charles\FacultyConnect-Sandbox\phase2-20261018\runtime.env'
$content = [IO.File]::ReadAllText($privateFile)
$tokens = [regex]::Matches($content, '(?m)^NGROK_AUTHTOKEN="?([^"\r\n]+)"?\r?$')
if ($tokens.Count -ne 1 -or [string]::IsNullOrWhiteSpace($tokens[0].Groups[1].Value)) {
    throw 'Enter one ngrok token privately in runtime.env first.'
}
$env:NGROK_AUTHTOKEN = $tokens[0].Groups[1].Value
$content = $null
$tokens = $null
Write-Output 'Authentication environment staged locally. No tunnel started or remote authentication attempted.'
