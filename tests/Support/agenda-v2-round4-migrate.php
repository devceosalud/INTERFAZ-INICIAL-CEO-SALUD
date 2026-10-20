<?php
if(PHP_SAPI!=='cli' || getenv('AGENDA_V2_ROUND4_SCHEMA')!=='LOCAL_FICTITIOUS_ONLY') throw new RuntimeException('Explicit QA-only schema flag required.');
require dirname(__DIR__,2).'/vendor/autoload.php';
$app=\Tests\Support\IsolatedAgendaV2Qa::boot(true);
$code=\Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
echo \Illuminate\Support\Facades\Artisan::output();
// Only the fictitious browser schema can have an earlier local draft of this uncommitted migration.
$column=\Illuminate\Support\Facades\DB::selectOne("SELECT DATETIME_PRECISION precision_value FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='agenda_professional_preferences' AND COLUMN_NAME='last_selected_at'");
if($column && (int)$column->precision_value!==6)\Illuminate\Support\Facades\DB::statement('ALTER TABLE agenda_professional_preferences MODIFY last_selected_at TIMESTAMP(6) NULL DEFAULT NULL');
exit($code);
