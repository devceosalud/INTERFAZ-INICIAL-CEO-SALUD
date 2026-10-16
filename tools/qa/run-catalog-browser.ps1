param([switch]$ResetSyntheticData)
$ErrorActionPreference='Stop'
$catalogRoot=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
Set-Location -LiteralPath $catalogRoot
if(Test-Path -LiteralPath '.env'){throw 'Use the separate catalogue worktree without .env'}
if(-not $ResetSyntheticData){throw 'Pass -ResetSyntheticData to authorize resetting ONLY ceosalud_qa_catalog on local port 33323'}
$php=(Resolve-Path '../entrega-urgente-1/storage/app/qa-final/equivalent/php-8.3.35/php.exe').Path
$evidence=Join-Path $catalogRoot 'storage/app/qa-catalog'
New-Item -ItemType Directory -Force -Path $evidence | Out-Null
foreach($port in @(8088,8089)){if(Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue){throw "QA port $port is occupied. Stop only a server you own before running this script."}}
$processes=@()
try {
 $password='catalog-qa-'+[guid]::NewGuid().ToString()
 @{password=$password} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $evidence 'private.json') -Encoding utf8
 $env:CATALOG_QA_PASSWORD=$password;$env:CATALOG_QA_RESET='OWNED_CATALOG_SYNTHETIC'
 & $php tests/Support/catalog-seed.php
 if($LASTEXITCODE -ne 0){throw 'Owned synthetic seed failed'}
 Remove-Item Env:CATALOG_QA_PASSWORD,Env:CATALOG_QA_RESET
 foreach($port in @(8088,8089)){
  $processes+=Start-Process -FilePath $php -ArgumentList @('-S',"127.0.0.1:$port",'-t','public','tests/Support/catalog-router.php') -WorkingDirectory $catalogRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $evidence "server-$port-out.txt") -RedirectStandardError (Join-Path $evidence "server-$port-error.txt")
 }
 # Wait only for the local sockets; this never probes production.
 foreach($port in @(8088,8089)){for($attempt=0;$attempt -lt 30;$attempt++){if(Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue){break};Start-Sleep -Milliseconds 200}}
 & node tools/qa/browser-catalog.cjs
 if($LASTEXITCODE -ne 0){throw 'Browser QA failed; inspect the owned evidence directory'}
} finally {
 Remove-Item Env:CATALOG_QA_PASSWORD,Env:CATALOG_QA_RESET -ErrorAction SilentlyContinue
 foreach($process in $processes){if(-not $process.HasExited){Stop-Process -Id $process.Id}}
}
