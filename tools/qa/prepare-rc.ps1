$ErrorActionPreference='Stop'
$rcRoot=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$worktrees=Split-Path $rcRoot -Parent
if(Test-Path -LiteralPath (Join-Path $rcRoot '.env')){throw 'RC QA refuses a checkout with .env'}
if(!(Test-Path -LiteralPath (Join-Path $rcRoot 'vendor'))){
    Copy-Item -LiteralPath (Join-Path $worktrees 'mvp-trabajadores-seguridad/vendor') -Destination (Join-Path $rcRoot 'vendor') -Recurse
}
$evidence=Join-Path $rcRoot 'storage/app/qa-rc'
New-Item -ItemType Directory -Path $evidence -Force | Out-Null
foreach($kind in @('init','setup')){
    $source=Join-Path $worktrees ('mvp-trabajadores-seguridad/storage/app/qa-workers/'+$kind+'-db.ps1')
    $script=[IO.File]::ReadAllText($source).Replace('.workers-mariadb-1189','.rc-mariadb-1189').Replace('33321','33322').Replace('ceosalud_qa_workers','ceosalud_qa_rc').Replace('workers-root-local-only','rc-root-local-only').Replace('workers-local-only','rc-local-only').Replace('worker_qa','rc_qa')
    [IO.File]::WriteAllText((Join-Path $evidence ($kind+'-db.ps1')),$script)
}
Write-Output 'RC dependency copy and guarded runtime scripts ready. No database initialized by this preparation.'
