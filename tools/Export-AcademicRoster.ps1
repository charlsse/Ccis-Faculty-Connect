param([string]$Source = 'C:\Users\Charles\Downloads\CCIS-Faculty-Roster-2026-2027.xlsx', [string]$Output = 'storage/app/private/academic-roster-review.json')
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
$stream = [IO.File]::Open($Source, 'Open', 'Read', 'ReadWrite')
$zip = New-Object IO.Compression.ZipArchive($stream, [IO.Compression.ZipArchiveMode]::Read)
try {
    function Read-Entry($name) {
        $reader = New-Object IO.StreamReader($zip.GetEntry($name).Open())
        try { [xml]$reader.ReadToEnd() } finally { $reader.Dispose() }
    }
    $shared = Read-Entry 'xl/sharedStrings.xml'
    $strings = @($shared.sst.si | ForEach-Object { $_.InnerText })
    $book = Read-Entry 'xl/workbook.xml'
    $rels = Read-Entry 'xl/_rels/workbook.xml.rels'
    $sheet = @($book.workbook.sheets.sheet | Where-Object { $_.name -eq 'FACULTY ROSTER' })
    if ($sheet.Count -ne 1) { throw 'Expected one FACULTY ROSTER sheet' }
    $rid = $sheet[0].GetAttribute('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')
    $target = ($rels.Relationships.Relationship | Where-Object { $_.Id -eq $rid }).Target.TrimStart('/')
    if (!$target.StartsWith('xl/')) { $target = 'xl/' + $target }
    $xml = Read-Entry $target
    $program = 'College of Computing and Information Sciences'
    $records = @()
    foreach ($row in $xml.worksheet.sheetData.row) {
        $cells = @{}
        foreach ($cell in $row.c) {
            $value = if ($cell.t -eq 's') { $strings[[int]$cell.v] } elseif ($cell.t -eq 'inlineStr') { $cell.is.InnerText } else { [string]$cell.v }
            $cells[($cell.r -replace '\d', '')] = $value.Trim()
        }
        if ($cells['B'] -match '^(Computer Science Program|IS and ITE Allied Program|IT Program|Diploma and Services Programs)$') { $program = $cells['B'] }
        if ($cells['B'] -eq 'Academic Support Staff') { break }
        if ($cells['A'] -notmatch '^\d+$' -or $cells['D'] -notin @('FULL-TIME','PART-TIME')) { continue }
        $number = [int]$cells['A']
        if ($number -ne $records.Count + 1 -or !$cells['B']) { throw 'Roster numbering or name conflict' }
        $display = $cells['B']
        $name = $display -replace '^(?:(?:Assoc\.|Asst\.)\s*)?Prof\.\s*', '' -replace ',\s*MIT$', ''
        if ($name -match '^(.+?)\s+([A-Z](?:[A-Z])?\.)\s+(.+)$') {
            $first = $Matches[1]; $middle = $Matches[2]; $last = $Matches[3]
        } elseif ($name -eq 'Emily Sicat') {
            $first = 'Emily'; $middle = $null; $last = 'Sicat'
        } else { throw "Name needs review at source row $($row.r)" }
        $records += [ordered]@{ roster_key = ('CCIS-AY2627-{0:D3}' -f $number); source_row = [int]$row.r; source_name = $display; first_name = $first; middle_name = $middle; last_name = $last; department = $program; position = $cells['C']; employment_classification = $cells['D'] }
    }
    if ($records.Count -ne 41 -or @($records | Where-Object { $_.employment_classification -eq 'FULL-TIME' }).Count -ne 30) { throw 'Expected 41 teaching faculty, 30 full-time' }
    $stream.Position = 0
    $hasher = [Security.Cryptography.SHA256]::Create()
    try { $sourceHash = ([BitConverter]::ToString($hasher.ComputeHash($stream))).Replace('-', '').ToLower() } finally { $hasher.Dispose() }
    $manifest = [ordered]@{ source_sha256 = $sourceHash; source_sheet = 'FACULTY ROSTER'; records = $records }
    [IO.File]::WriteAllText((Join-Path (Get-Location) $Output), ($manifest | ConvertTo-Json -Depth 6), (New-Object Text.UTF8Encoding($false)))
    Write-Output 'Validated 41 teaching faculty: 30 full-time, 11 part-time. No emails or official faculty IDs inferred.'
} finally { $zip.Dispose(); $stream.Dispose() }
