param([string]$PhpPath, [switch]$RestartOwnedServer)
$ErrorActionPreference='Stop'
$qaRoot=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '../..')).Path
$qaState=Join-Path $qaRoot 'storage/app/qa-v2/manual-server.json'
if(Test-Path -LiteralPath (Join-Path $qaRoot '.env')){throw 'QA refuses a checkout containing .env.'}
$qaRecord=Get-Content -LiteralPath $qaState -Raw | ConvertFrom-Json
if(!$PhpPath){$PhpPath=$qaRecord.executable}
$qaPhp=(Resolve-Path -LiteralPath $PhpPath).Path
if($RestartOwnedServer){& (Join-Path $qaRoot 'storage/app/qa-v2/stop-manual-qa.ps1')}
$qaListener=Get-NetTCPConnection -LocalPort 8092 -State Listen -ErrorAction SilentlyContinue
if($qaListener){
 $qaCurrent=Get-Process -Id $qaRecord.pid -ErrorAction Stop
 if($qaListener.OwningProcess -ne $qaRecord.pid -or $qaCurrent.Path -ne $qaRecord.executable -or $qaCurrent.StartTime.ToUniversalTime().Ticks.ToString() -ne $qaRecord.startTimeUtcTicks){throw 'Port 8092 belongs to another process.'}
 Write-Output 'Owned QA server already available at http://127.0.0.1:8092';exit
}
Push-Location $qaRoot
try{
 & $qaPhp tests/Support/agenda-v2-health.php
 if($LASTEXITCODE -ne 0){throw 'Local QA identity check failed.'}
 $qaProcess=Start-Process -FilePath $qaPhp -ArgumentList @('-d','upload_max_filesize=8M','-d','post_max_size=10M','-S','127.0.0.1:8092','-t','public','tests/Support/agenda-v2-browser-router.php') -WorkingDirectory $qaRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $qaRoot 'storage/app/qa-v2/manual-server-out.txt') -RedirectStandardError (Join-Path $qaRoot 'storage/app/qa-v2/manual-server-error.txt')
 $qaRecord.pid=$qaProcess.Id;$qaRecord.startTimeUtcTicks=$qaProcess.StartTime.ToUniversalTime().Ticks.ToString();$qaRecord.executable=$qaPhp
 $qaRecord | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $qaState -Encoding utf8
 $qaReady=$null
 for($qaAttempt=0;$qaAttempt -lt 20;$qaAttempt++){
  $qaReady=Get-NetTCPConnection -LocalPort 8092 -State Listen -ErrorAction SilentlyContinue
  if($qaReady){break};Start-Sleep -Milliseconds 100
 }
 if(!$qaReady -or $qaReady.OwningProcess -ne $qaProcess.Id){throw 'Owned server did not bind port 8092. Inspect the local server logs.'}
 Write-Output ('Persistent local QA server started: http://127.0.0.1:8092; PID '+$qaProcess.Id+'. Stop using storage/app/qa-v2/stop-manual-qa.ps1.')
}finally{Pop-Location}
