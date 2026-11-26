param([Parameter(Mandatory=$true)][string]$Directory)
$ErrorActionPreference = 'Stop'
$target = Get-Item -LiteralPath $Directory
if (!$target.PSIsContainer -or ($target.Attributes -band [IO.FileAttributes]::ReparsePoint)) { throw 'Expected a real protected directory' }
$acl = Get-Acl -LiteralPath $target.FullName
$allowed = @([Security.Principal.WindowsIdentity]::GetCurrent().User.Value, 'S-1-5-18', 'S-1-5-32-544')
if (!$acl.AreAccessRulesProtected) { throw 'Directory must have inheritance disabled' }
foreach ($rule in $acl.Access) {
    $sid = $rule.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value
    if ($rule.AccessControlType -eq 'Allow' -and $sid -notin $allowed) { throw 'Directory permits an unauthorized identity' }
}
Write-Output 'Protected directory ACL verified.'
