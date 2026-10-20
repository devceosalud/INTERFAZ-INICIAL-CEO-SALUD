param([switch]$ResetSyntheticData, [string]$PhpPath)
$ErrorActionPreference='Stop'
$qaRoot=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
Set-Location -LiteralPath $qaRoot
if(Test-Path -LiteralPath '.env'){throw 'Use a separate QA worktree without .env.'}
if(-not $ResetSyntheticData){throw 'Pass -ResetSyntheticData to reset ONLY the owned browser schema on 33324.'}
if(-not $PhpPath){$PhpPath=(Resolve-Path '../entrega-urgente-1/storage/app/qa-final/equivalent/php-8.3.35/php.exe').Path}
if(Get-NetTCPConnection -LocalPort 8092 -State Listen -ErrorAction SilentlyContinue){throw 'Local port 8092 is occupied; do not stop an unowned server.'}
$qaOut=Join-Path $qaRoot 'storage/app/qa-v2'
New-Item -ItemType Directory -Path $qaOut -Force | Out-Null
$qaServer=$null
try {
    $env:AGENDA_V2_QA_PASSWORD='v2-browser-'+[guid]::NewGuid().ToString()
    $env:AGENDA_V2_QA_RESET='OWNED_SYNTHETIC_V2'
    @{password=$env:AGENDA_V2_QA_PASSWORD} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $qaOut 'browser-private.json') -Encoding utf8
    & $PhpPath tests/Support/agenda-v2-browser-seed.php
    if($LASTEXITCODE -ne 0){throw 'Owned synthetic seed failed.'}
    Remove-Item Env:AGENDA_V2_QA_PASSWORD,Env:AGENDA_V2_QA_RESET
    $qaServer=Start-Process -FilePath $PhpPath -ArgumentList @('-S','127.0.0.1:8092','-t','public','tests/Support/agenda-v2-browser-router.php') -WorkingDirectory $qaRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $qaOut 'browser-server-out.txt') -RedirectStandardError (Join-Path $qaOut 'browser-server-error.txt')
    for($attempt=0;$attempt -lt 30;$attempt++){
        if(Get-NetTCPConnection -LocalPort 8092 -State Listen -ErrorAction SilentlyContinue){break}
        Start-Sleep -Milliseconds 200
    }
    & node tools/qa/browser-agenda-v2.cjs
    if($LASTEXITCODE -ne 0){throw 'Browser QA failed; inspect the owned evidence directory.'}
} finally {
    Remove-Item Env:AGENDA_V2_QA_PASSWORD,Env:AGENDA_V2_QA_RESET -ErrorAction SilentlyContinue
    if($qaServer -and -not $qaServer.HasExited){Stop-Process -Id $qaServer.Id}
}
