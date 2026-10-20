<?php
if(PHP_SAPI!=='cli')throw new RuntimeException('CLI only.');
require dirname(__DIR__,2).'/vendor/autoload.php';
\Tests\Support\IsolatedAgendaV2Qa::boot(true);
$file=storage_path('app/qa-v2/browser-clock.json');
if(($argv[1]??'')==='--real-time'){
    if(is_file($file))unlink($file);
    echo "QA replay clock disabled; next requests use real local time.\n";exit;
}
if(($argv[1]??'')!=='--use-fixture-date')throw new RuntimeException('Use --use-fixture-date or --real-time.');
$f=json_decode(file_get_contents(storage_path('app/qa-v2/round4/fixtures.json')),true,512,JSON_THROW_ON_ERROR);
$date=\Carbon\Carbon::createFromFormat('!Y-m-d',$f['date'],'America/Lima');
if($date->format('Y-m-d')!==$f['date'])throw new RuntimeException('Invalid fictional fixture day.');
file_put_contents($file,json_encode(['scope'=>'LOCAL_FICTITIOUS_CLOCK_ONLY','date'=>$f['date']],JSON_PRETTY_PRINT));
echo "QA browser replay day: ".$f['date']." America/Lima, 23:59. No patient rows or server clock changed.\n";
