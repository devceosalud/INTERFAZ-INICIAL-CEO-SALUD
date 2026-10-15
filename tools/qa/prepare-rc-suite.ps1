$ErrorActionPreference='Stop'
$rcRoot=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
Set-Location -LiteralPath $rcRoot
$evidence=Join-Path $rcRoot 'storage/app/qa-rc'
foreach($kind in @('init','setup')){
 $script=[IO.File]::ReadAllText((Join-Path $evidence ($kind+'-db.ps1'))).Replace('.rc-mariadb-1189','.rc-suite-mariadb-1189').Replace('33322','33323').Replace('ceosalud_qa_rc','ceosalud_qa_rc_suite').Replace('rc-root-local-only','rc-suite-root-local-only').Replace('rc-local-only','rc-suite-local-only').Replace('rc_qa','rc_suite_qa')
 [IO.File]::WriteAllText((Join-Path $evidence ($kind+'-suite-db.ps1')),$script)
}
$helper=[IO.File]::ReadAllText((Join-Path $rcRoot 'tests/Support/IsolatedRcQa.php')).Replace('IsolatedRcQa','IsolatedRcSuiteQa').Replace('.rc-mariadb-1189','.rc-suite-mariadb-1189').Replace('33322','33323').Replace('ceosalud_qa_rc','ceosalud_qa_rc_suite').Replace('rc-local-only','rc-suite-local-only').Replace('rc_qa','rc_suite_qa').Replace('qa-rc-','qa-rc-suite-')
[IO.File]::WriteAllText((Join-Path $rcRoot 'tests/Support/IsolatedRcSuiteQa.php'),$helper)
$profile=[IO.File]::ReadAllText((Join-Path $rcRoot 'phpunit.rc.xml')).Replace('rc-11.8.9','rc-suite-11.8.9').Replace('33322','33323').Replace('ceosalud_qa_rc','ceosalud_qa_rc_suite').Replace('rc-local-only','rc-suite-local-only').Replace('rc_qa','rc_suite_qa')
[IO.File]::WriteAllText((Join-Path $rcRoot 'phpunit.rc-suite.xml'),$profile)
Write-Output 'Separate hard-guarded suite runtime prepared. Browser database 33322 is unchanged.'
