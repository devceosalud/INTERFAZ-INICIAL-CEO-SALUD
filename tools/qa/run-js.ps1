param([string]$Report='full-js-final.txt')
$ErrorActionPreference='Stop'
if($Report -notmatch '^[a-z0-9-]+\.txt$'){throw 'Safe report filename required'}
$rcRoot=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
Set-Location -LiteralPath $rcRoot
$tests=@(Get-ChildItem -LiteralPath 'tests/JavaScript' -Filter '*.test.js' -File | ForEach-Object {$_.FullName})
if($tests.Count -lt 10){throw 'JavaScript test discovery failed'}
& node --test @tests *> ('storage/app/qa-rc/'+$Report)
exit $LASTEXITCODE
