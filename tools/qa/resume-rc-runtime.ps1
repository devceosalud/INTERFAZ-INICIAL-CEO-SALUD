$ErrorActionPreference='Stop'
$rcRoot=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
Set-Location -LiteralPath $rcRoot
$worktrees=Split-Path $rcRoot -Parent
$server=Join-Path $worktrees '.qa-mariadb1189/bin/mariadbd.exe'
foreach($entry in @(@{port=33322;folder='.rc-mariadb-1189'},@{port=33323;folder='.rc-suite-mariadb-1189'})){
 $runtime=Join-Path $worktrees $entry.folder
 $configuration=(Resolve-Path -LiteralPath (Join-Path $runtime 'server.ini')).Path
 if(!(Test-Path -LiteralPath (Join-Path $runtime 'data/mysql'))){throw 'Existing owned data directory required; never initialize here'}
 if(!(Get-NetTCPConnection -State Listen -LocalPort $entry.port -ErrorAction SilentlyContinue)){
  $daemon=Start-Process -FilePath $server -ArgumentList ('--defaults-file='+$configuration) -WindowStyle Hidden -PassThru
  Write-Output ('Resumed existing isolated MariaDB '+$entry.port+' PID '+$daemon.Id)
 }
}
$php=(Resolve-Path '..\entrega-urgente-1\storage\app\qa-final\equivalent\php-8.3.35\php.exe').Path
foreach($pair in @(@{ports=@(8026,8027);router='tests/Support/rc-browser-router.php';file='http-processes.json'},@{ports=@(8028,8029);router='tests/Support/rc-load-router.php';file='load-processes.json'})){
 $records=@()
 foreach($port in $pair.ports){
  $listener=Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction SilentlyContinue
  if($listener){
   $owned=Get-CimInstance Win32_Process -Filter ('ProcessId='+$listener.OwningProcess)
   if(!$owned.CommandLine.Contains($pair.router)){throw 'Port belongs to another application; no process touched'}
   $pidValue=$listener.OwningProcess
  }else{
   $process=Start-Process -FilePath $php -ArgumentList @('-S',('127.0.0.1:'+$port),'-t','public',$pair.router) -WorkingDirectory $rcRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput ('storage/app/qa-rc/resumed-'+$port+'-out.log') -RedirectStandardError ('storage/app/qa-rc/resumed-'+$port+'-error.log')
   $pidValue=$process.Id
  }
  $records+=@{port=$port;process_id=$pidValue}
 }
 $records | ConvertTo-Json | Set-Content -LiteralPath ('storage/app/qa-rc/'+$pair.file) -Encoding UTF8
}
Write-Output 'Existing QA data preserved; no migration, fixture reset or production connection.'
