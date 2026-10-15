$ErrorActionPreference='Stop'
$rcRoot=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
Set-Location -LiteralPath $rcRoot
$php=(Resolve-Path '..\entrega-urgente-1\storage\app\qa-final\equivalent\php-8.3.35\php.exe').Path
foreach($size in @(100,500,2000)){
 & $php tests/Support/rc-nicole-benchmark.php ('http-'+$size) --suite --reset-owned ('--size='+$size) --prepare-http
 if($LASTEXITCODE -ne 0){throw 'Guarded load fixture failed'}
 & node tools/qa/nicole-http-load.cjs *> ('storage/app/qa-rc/performance-http-'+$size+'.log')
 if($LASTEXITCODE -ne 0){throw ('Real HTTP measurement failed for '+$size+'; preserved raw output')}
 Write-Output ('Completed actual HTTP measurement: '+$size)
}
