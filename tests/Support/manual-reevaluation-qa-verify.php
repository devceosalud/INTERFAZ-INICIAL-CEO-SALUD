<?php
if(PHP_SAPI!=='cli')exit('CLI only.');
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=\Tests\Support\IsolatedManualReevaluationQa::boot();
$before=json_decode(file_get_contents(storage_path('app/hotfix-qa/browser-before.json')),true);
$after=[];foreach(array_keys($before) as $table)$after[$table]=\Illuminate\Support\Facades\DB::table($table)->orderBy('id')->get()->toArray();
if(json_encode($before)!==json_encode($after)){fwrite(STDERR,"Browser QA changed protected records or snapshot runtime types differ. Use the same PHP runtime as seed.\n");exit(1);}
$result=['protected_records_unchanged'=>true,'appointments'=>\App\Models\Appointment::count(),
    'payments'=>\App\Models\Payment::count(),'manual_events'=>\App\Models\AppointmentEvent::whereIn('event_type',[
        \App\Services\Scheduling\ManualReevaluationService::MARKED,\App\Services\Scheduling\ManualReevaluationService::CLEARED])->count(),
    'booking_state_unchanged'=>\App\Models\Appointment::findOrFail(1)->estado_agenda==='LEGADO'];
echo json_encode($result,JSON_PRETTY_PRINT).PHP_EOL;
