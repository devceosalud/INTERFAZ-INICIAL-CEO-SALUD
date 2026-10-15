$ErrorActionPreference='Stop'
$rcRoot=Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
Set-Location -LiteralPath $rcRoot
$source=[IO.File]::ReadAllText((Join-Path $rcRoot 'tests/Support/IsolatedWorkerQa.php'))
$source=$source.Replace('IsolatedWorkerQa','IsolatedRcQa').Replace('.workers-mariadb-1189','.rc-mariadb-1189').Replace('33321','33322').Replace('8025','8026').Replace('ceosalud_qa_workers','ceosalud_qa_rc').Replace('worker_qa','rc_qa').Replace('workers-local-only','rc-local-only').Replace('workers_qa_only','rc_qa_only').Replace('qa-workers-','qa-rc-')
[IO.File]::WriteAllText((Join-Path $rcRoot 'tests/Support/IsolatedRcQa.php'),$source)
foreach($name in @('IsolatedAgendaQa','IsolatedWorkerQa')){
    $file=Join-Path $rcRoot ('tests/Support/'+$name+'.php')
    $text=[IO.File]::ReadAllText($file)
    $needle=if($name -eq 'IsolatedAgendaQa'){'        $root = dirname(__DIR__, 2);'}else{'        $root=dirname(__DIR__,2);'}
    $insert="        if(getenv('AGENDA_QA_RUNTIME')==='rc-11.8.9')return IsolatedRcQa::boot(`$browser);`n"
    if(!$text.Contains($needle)){throw ('Missing guarded insertion '+$name)}
    [IO.File]::WriteAllText($file,$text.Replace($needle,$insert+$needle))
}
$xml=[IO.File]::ReadAllText((Join-Path $rcRoot 'phpunit.workers.xml'))
$xml=$xml.Replace('workers-11.8.9','rc-11.8.9').Replace('33321','33322').Replace('ceosalud_qa_workers','ceosalud_qa_rc').Replace('worker_qa','rc_qa').Replace('workers-local-only','rc-local-only').Replace('qa-workers-','qa-rc-')
$xml=[regex]::Replace($xml,'<file>./tests/Integration/WorkerAccessMariaDbTest.php</file>\s*<file>./tests/Integration/WorkerConcurrencyTest.php</file>','<directory suffix="Test.php">./tests/Integration</directory>')
[IO.File]::WriteAllText((Join-Path $rcRoot 'phpunit.rc.xml'),$xml)
Write-Output 'RC strict PHP profile prepared for 33322 only; former runtimes preserved.'
