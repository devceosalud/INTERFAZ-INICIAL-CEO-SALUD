<?php
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__,2).'/vendor/autoload.php';\Tests\Support\RescheduleVisualQa::boot();
use App\Models\Appointment;
use App\Services\Scheduling\AppointmentReevaluationService as R;
use App\Services\Billing\AppointmentEconomicPosition;
use Illuminate\Support\Facades\DB;
$f=json_decode(file_get_contents(storage_path('app/qa-reschedule-visual/integration-fixture.json')),true,512,JSON_THROW_ON_ERROR);
$e=json_decode(file_get_contents(storage_path('app/qa-reschedule-visual/economy-fixture.json')),true,512,JSON_THROW_ON_ERROR);
$checks=[];$check=function($condition,$name)use(&$checks){if(!$condition)throw new RuntimeException($name);$checks[]=$name;};
foreach(['paid','normal','manual','declined'] as $key){$a=Appointment::findOrFail($f['cases'][$key]);$p=app(AppointmentEconomicPosition::class)->forAppointment($a);
    $check($a->estado_cita==='ATENDIDO' && $a->hora_salida!==null,$key.': atendido y salida separados');
    $check($p['price_cents']===12000 && $p['paid_cents']===($key==='paid'?6000:0) && $p['balance_cents']===($key==='paid'?6000:12000),$key.': dinero original conservado');
    $resumed=$a->events()->where('event_type',R::MEDICAL_RESUMED)->count();$check($resumed===(in_array($key,['paid','manual'])?1:0),$key.': solo retorno real genera segunda atención');
    if(in_array($key,['manual','declined']))$check(DB::table('appointment_requested_procedures')->where('appointment_id',$a->id)->whereNull('completed_at')->where('selected',true)->count()===3,$key.': tres procedimientos pendientes conservados');
}
$a=Appointment::findOrFail($f['cases']['without_deposit']);$p=app(AppointmentEconomicPosition::class)->forAppointment($a);
$check($a->estado_agenda==='CONFIRMADA' && $a->estado_pagado==='PENDIENTE' && !$a->es_exonerado && $p['paid_cents']===0 && $p['balance_cents']===12000,'Excepción sin adelanto conserva pago y saldo reales');
$check($a->events()->where('event_type','CONFIRMACION_SIN_ADELANTO')->count()===1,'Una autorización real y auditada sin adelanto');
$check(DB::table('voucher_items')->where('item_id',$a->id)->whereIn('item_type',['cita',Appointment::class])->count()===0,'Sin voucher ficticio para confirmar sin adelanto');
$check(Appointment::findOrFail($f['cases']['private'])->estado_agenda==='PENDIENTE_CONFIRMACION','Reserva privada no alterada');
$a=Appointment::findOrFail($e['paid_id']);$p=app(AppointmentEconomicPosition::class)->forAppointment($a);
$check(substr($a->fecha_cita,0,10)==='2026-10-24' && (int)$a->duracion_cita===20,'Reprogramación conserva identidad y duración');
$check($p['price_cents']===10000 && $p['paid_cents']===6000 && $p['balance_cents']===4000,'Descuento autorizado conserva pago60 y recalcula saldo40');
$check(count($p['payment_details'])===1,'Corrección y reprogramación mantienen un único pago');
file_put_contents(base_path('docs/recuperacion-cinco/qa/integration/data-integrity.json'),json_encode(['result'=>'PASS','checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo 'PASS: '.count($checks)." controles de integridad en cohortes ficticias.\n";
