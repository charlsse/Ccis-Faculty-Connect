param([Parameter(Mandatory=$true)][string]$Directory)
$ErrorActionPreference = 'Stop'
$target = Get-Item -LiteralPath $Directory
if (!$target.PSIsContainer -or ($target.Attributes -band [IO.FileAttributes]::ReparsePoint) -or @(Get-ChildItem -LiteralPath $Directory -Force).Count -gt 0) { throw 'Only a new empty real directory may be protected' }
$current = [Security.Principal.WindowsIdentity]::GetCurrent().User
$acl = New-Object Security.AccessControl.DirectorySecurity
$acl.SetAccessRuleProtection($true, $false)
$acl.SetOwner($current)
foreach ($sid in @($current.Value, 'S-1-5-18', 'S-1-5-32-544')) {
    $identity = New-Object Security.Principal.SecurityIdentifier($sid)
    $rule = New-Object Security.AccessControl.FileSystemAccessRule($identity, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow')
    $acl.AddAccessRule($rule)
}
Set-Acl -LiteralPath $target.FullName -AclObject $acl
& (Join-Path $PSScriptRoot 'Test-PrivateRosterDirectory.ps1') -Directory $target.FullName
