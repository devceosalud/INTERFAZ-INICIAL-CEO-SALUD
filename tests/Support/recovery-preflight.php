<?php
if(PHP_SAPI!=='cli')throw new RuntimeException('CLI only');
require dirname(__DIR__,2).'/vendor/autoload.php';
\Tests\Support\RescheduleVisualQa::boot();
$checks=[];
foreach(['route:cache','view:cache','route:clear','view:clear','config:cache','config:clear'] as $command){
    $status=\Illuminate\Support\Facades\Artisan::call($command);
    $checks[$command]=$status;
    if($status!==0)throw new RuntimeException($command.' failed');
}
echo json_encode(['result'=>'PASS','checks'=>$checks],JSON_PRETTY_PRINT).PHP_EOL;
