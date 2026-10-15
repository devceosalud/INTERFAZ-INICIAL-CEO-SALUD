$ErrorActionPreference='Stop'
$rcRoot=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
Set-Location -LiteralPath $rcRoot
$php=(Resolve-Path '..\entrega-urgente-1\storage\app\qa-final\equivalent\php-8.3.35\php.exe').Path
$records=@()
foreach($port in @(8028,8029)){
 if(Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction SilentlyContinue){throw ('Port occupied; preserved: '+$port)}
 $server=Start-Process -FilePath $php -ArgumentList @('-S',('127.0.0.1:'+$port),'tests/Support/rc-load-router.php') -WorkingDirectory $rcRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput ('storage/app/qa-rc/load-'+$port+'-out.log') -RedirectStandardError ('storage/app/qa-rc/load-'+$port+'-error.log')
 $records+=@{port=$port;process_id=$server.Id}
}
$records | ConvertTo-Json | Set-Content -LiteralPath 'storage/app/qa-rc/load-processes.json' -Encoding UTF8
Write-Output 'Owned suite HTTP servers 8028/8029 started, database 33323 only.'
