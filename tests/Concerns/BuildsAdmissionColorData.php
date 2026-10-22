<?php
namespace Tests\Concerns;

use App\Models\AppointmentOperationalReview;
use App\Services\Scheduling\{AppointmentHistory,AppointmentReevaluationService as R};
use Illuminate\Support\Facades\DB;

/** Synthetic read-only visual matrix. No real identity, calendar or financial data. */
trait BuildsAdmissionColorData
{
    use BuildsRescheduleVisualData;
    protected function admissionColorFixture():array
    {
        $f=$this->rescheduleVisualFixture();$actor=$f['actor'];$catalog=$f['catalog'];
        $admin=$this->agendaReader('ADMINISTRADOR');$commercial=$this->agendaReader('COMERCIAL');
        $medical=$this->createUserWithRole('MEDICO');$medical->forceFill(['doctor_id'=>$catalog['doctor']->id])->save();
        $cases=[];$i=0;
        $definitions=[
            'PROGRAMADA_SIN_PAGO'=>['PROGRAMADO','SIN_PAGO_REAL','#fdf0d9',[]],
            'PAGO_REAL'=>['PROGRAMADO','PAGO_REAL','#fff2a6',[]],
            'EXONERADA'=>['PROGRAMADO','SIN_PAGO_REAL','#fdf0d9',['es_exonerado'=>true,'autorizado_por'=>'AUTORIZADOR FICTICIO','motivo_exoneracion'=>'EXONERACION FICTICIA','precio_programado'=>0,'saldo_pendiente'=>0]],
            'LLEGADA'=>['PACIENTE_LLEGO','PRESENTE','#99f6e4',['hora_llegada'=>now()]],
            'EN_ESPERA'=>['EN_ESPERA','PRESENTE','#99f6e4',['hora_llegada'=>now()]],
            'EN_ATENCION'=>['EN_ATENCION','PRESENTE','#99f6e4',['hora_llegada'=>now(),'hora_atencion'=>now()]],
            'REEVALUACION_PENDIENTE'=>['EN_ATENCION','PRESENTE','#99f6e4',['hora_llegada'=>now(),'hora_atencion'=>now()]],
            'REEVALUACION_LISTA'=>['EN_ATENCION','LISTO_REEVALUACION','#ffc28a',['hora_llegada'=>now(),'hora_atencion'=>now()]],
            'FINALIZADA'=>['ATENDIDO','ATENDIDO','#74dccb',['hora_llegada'=>now(),'hora_atencion'=>now(),'hora_atendido'=>now()]],
            'SALIDA'=>['ATENDIDO','SALIDA_REGISTRADA','#99f6e4',['hora_llegada'=>now(),'hora_atencion'=>now(),'hora_atendido'=>now(),'hora_salida'=>now()]],
            'CANCELADA'=>['CANCELADO','DESACTIVADA','#fecaca',[]],
            'PROCEDIMIENTOS_INCOMPLETOS'=>['EN_ATENCION','PRESENTE','#99f6e4',['hora_llegada'=>now(),'hora_atencion'=>now()]],
            'BLANCA_SIN_PAGO'=>['REEVALUACION','SIN_PAGO_REAL','#ffffff',[]],
        ];
        foreach($definitions as $name=>[$state,$key,$background,$attributes]){
            $a=$name==='PAGO_REAL'?$f['a']:$this->lifecycleAppointment($actor,$catalog,$attributes+[
                'fecha_cita'=>'2026-10-21','hora_cita'=>sprintf('%02d:%02d',8+intdiv($i*15,60),($i*15)%60),
                'estado_agenda'=>'CONFIRMADA','estado_cita'=>$state]);
            if($attributes)$a->forceFill($attributes)->save();
            $a->patient->update(['nombre'=>$name,'apellido_paterno'=>'PACIENTE FICTICIO','apellido_materno'=>'QA COLORES']);
            if(in_array($name,['REEVALUACION_PENDIENTE','REEVALUACION_LISTA','PROCEDIMIENTOS_INCOMPLETOS'],true)){
                app(AppointmentHistory::class)->record($a,R::INDICATED,$medical->id,['motivo'=>'INDICACION FICTICIA',
                    'metadata'=>['version'=>1,'source'=>'MEDICAL_RETURN_SAME_APPOINTMENT_V1','doctor_id'=>(int)$a->doctor_id]]);
            }
            if(in_array($name,['REEVALUACION_LISTA','PROCEDIMIENTOS_INCOMPLETOS'],true)){
                $completed=$name==='REEVALUACION_LISTA'?3:2;
                AppointmentOperationalReview::create(['appointment_id'=>$a->id,'version'=>1,'requested_count'=>3,'completed_count'=>$completed,
                    'status'=>$completed===3?'READY':'NOT_READY','origin'=>$completed===3?'AUTOMATIC':null,
                    'ready_at'=>$completed===3?now():null,'decided_by_user_id'=>$completed===3?$actor->id:null]);
                foreach(['radiography','laboratory','wound_care'] as $index=>$procedure)DB::table('appointment_requested_procedures')->insert([
                    'appointment_id'=>$a->id,'procedure_key'=>$procedure,'selected'=>true,'requested_by_user_id'=>$medical->id,'requested_at'=>now(),
                    'verified_by_user_id'=>$index<$completed?$actor->id:null,'completed_at'=>$index<$completed?now():null,'created_at'=>now(),'updated_at'=>now()]);
            }
            $cases[]=['id'=>$a->id,'name'=>$name,'state'=>$state,'key'=>$key,'background'=>$background];$i++;
        }
        return $f+['admin'=>$admin,'commercial'=>$commercial,'medical'=>$medical,'cases'=>$cases];
    }
}
