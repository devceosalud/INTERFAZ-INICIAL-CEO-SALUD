<?php
if(PHP_SAPI!=='cli'||getenv('AGENDA_MANAGEMENT_QA')!=='APPEND_FICTIONAL_ONLY')throw new RuntimeException('Explicit fictional append only.');
require dirname(__DIR__,2).'/vendor/autoload.php';Tests\Support\IsolatedAgendaV2Qa::boot(true);
$f=json_decode(file_get_contents(storage_path('app/qa-v2/round3-regression/fixtures.json')),true,512,JSON_THROW_ON_ERROR);
$day=Carbon\Carbon::parse($f['date']);$last=App\Models\DoctorSchedule::where('doctor_id',$f['doctor_id'])->whereDate('fecha_cita',$f['date'])->orderByDesc('hora_fin')->firstOrFail();
$start=Carbon\Carbon::parse($f['date'].' '.$last->hora_fin)->addMinutes(30);$end=$start->copy()->addHour();if($end->toDateString()!==$f['date'])throw new RuntimeException('No safe same-day fictional extension remains.');
$s=App\Models\DoctorSchedule::create(['doctor_id'=>$f['doctor_id'],'site_id'=>$last->site_id,'fecha_cita'=>$f['date'],'dia_semana'=>$day->dayOfWeekIso,'hora_inicio'=>$start->format('H:i'),'hora_fin'=>$end->format('H:i'),'duracion_cita'=>30,'estado'=>'ACTIVO']);
file_put_contents(storage_path('app/qa-v2/management/regression-extra-slot.json'),json_encode(['schedule_id'=>$s->id,'doctor_id'=>$f['doctor_id'],'date'=>$f['date'],'start'=>$start->format('H:i'),'end'=>$end->format('H:i'),'reason'=>'Append-only fictional QA: old cohort exhausted; no appointments removed.'],JSON_PRETTY_PRINT));echo 'Fictional regression block appended; prior data retained.',PHP_EOL;
