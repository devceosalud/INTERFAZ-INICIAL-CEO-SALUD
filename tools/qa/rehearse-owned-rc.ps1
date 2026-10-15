$ErrorActionPreference='Stop'
$rcRoot=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
Set-Location -LiteralPath $rcRoot
$evidence=Join-Path $rcRoot 'storage/app/qa-rc'
$backup=Get-ChildItem -LiteralPath $evidence -Directory -Filter 'backup-restore-*' | Where-Object {Test-Path -LiteralPath (Join-Path $_.FullName 'backup-result.json')} | Select-Object -First 1
if(!$backup){throw 'Verified independent backup/restore required before replacing fictitious jornada fixture'}
$processes=Get-Content -LiteralPath (Join-Path $evidence 'http-processes.json') -Raw | ConvertFrom-Json
foreach($record in $processes){
 if(@(8026,8027) -notcontains [int]$record.port){throw 'Unexpected server port'}
 $process=Get-CimInstance Win32_Process -Filter ('ProcessId='+$record.process_id)
 if($process -and (!$process.CommandLine.Contains('tests/Support/rc-browser-router.php') -or !$process.CommandLine.Contains('127.0.0.1:'+$record.port))){throw 'PID no longer belongs to this QA server; refusing stop'}
 if($process){Stop-Process -Id $record.process_id}
}
$archive=Join-Path $evidence ('rounds/before-final-'+[guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $archive | Out-Null
foreach($file in (Get-ChildItem -LiteralPath $evidence -File)){
 if($file.Name -match '^(browser-|private-session-|jornada-|prices-|reevaluation-|historical-before|permissions-schedules-db|schedule-edit-db|http-802[67]-)' -or $file.Extension -eq '.png'){
  $resolved=[IO.Path]::GetFullPath($file.FullName)
  if(!$resolved.StartsWith($evidence+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)){throw 'Archive path outside QA evidence'}
  Move-Item -LiteralPath $resolved -Destination (Join-Path $archive $file.Name)
 }
}
& (Join-Path $PSScriptRoot 'start-rc-browser.ps1')
if($LASTEXITCODE -ne 0){throw 'Owned fixture setup failed'}
Write-Output ('Earlier fictitious jornada evidence preserved at '+$archive)
