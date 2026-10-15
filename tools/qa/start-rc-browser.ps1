$ErrorActionPreference='Stop'
$rcRoot=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
Set-Location -LiteralPath $rcRoot
$evidence=Join-Path $rcRoot 'storage/app/qa-rc'
foreach($port in @(8026,8027)){if(Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction SilentlyContinue){throw ('Port already occupied: '+$port+'; no reset performed')}}
$privatePath=Join-Path $evidence 'browser-private.json'
if(!(Test-Path -LiteralPath $privatePath)){
    @{password=([guid]::NewGuid().ToString('N')+[guid]::NewGuid().ToString('N'))} | ConvertTo-Json | Set-Content -LiteralPath $privatePath -Encoding UTF8
}
$private=Get-Content -LiteralPath $privatePath -Raw | ConvertFrom-Json
$env:RC_QA_BROWSER_PASSWORD=$private.password;$env:RC_QA_RESET='YES_OWNED_33322'
$php=(Resolve-Path '..\entrega-urgente-1\storage\app\qa-final\equivalent\php-8.3.35\php.exe').Path
& $php 'tests/Support/rc-browser-seed.php'
if($LASTEXITCODE -ne 0){throw 'Owned RC seed failed; no HTTP servers started'}
$records=@()
foreach($port in @(8026,8027)){
    $server=Start-Process -FilePath $php -ArgumentList @('-S',('127.0.0.1:'+$port),'-t','public','tests/Support/rc-browser-router.php') -WorkingDirectory $rcRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $evidence ('http-'+$port+'-out.log')) -RedirectStandardError (Join-Path $evidence ('http-'+$port+'-error.log'))
    $records+=@{port=$port;process_id=$server.Id}
}
$records | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $evidence 'http-processes.json') -Encoding UTF8
Write-Output 'Two owned RC HTTP processes listening only on 127.0.0.1:8026/8027. Password not printed.'
