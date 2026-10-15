<?php
namespace App\Services\Scheduling;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\User;
use App\Support\Scheduling\AppointmentOccupancy;
use App\Support\Scheduling\DoctorScheduleAccess;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Coordinates with booking/payment doctor locks. Never rewrites an appointment. */
class DoctorScheduleMutation
{
    public static function snapshot(DoctorSchedule $s):array{
        return ['id'=>(int)$s->id,'doctor_id'=>(int)$s->doctor_id,'site_id'=>$s->site_id===null?null:(int)$s->site_id,
            'date'=>$s->fecha_cita===null?null:substr($s->fecha_cita,0,10),'weekday'=>(int)$s->dia_semana,
            'start'=>substr($s->hora_inicio,0,5),'end'=>substr($s->hora_fin,0,5),'duration'=>(int)$s->duracion_cita,
            'state'=>$s->estado,'lock_version'=>(int)$s->lock_version];
    }
    public static function version(DoctorSchedule $s):string{return hash_hmac('sha256',json_encode(self::snapshot($s)),config('app.key'));}
    public function created(DoctorSchedule $s,int $actor):void{$this->audit($s,$actor,'CREATED',null,null);}
    private function audit(DoctorSchedule $s,int $actor,string $event,?array $before,?string $version):void{
        DB::table('doctor_schedule_events')->insert(['doctor_schedule_id'=>$s->id,'actor_user_id'=>$actor,'event_type'=>$event,
            'before'=>$before===null?null:json_encode($before),'after'=>json_encode(self::snapshot($s)),
            'version_before'=>$version,'version_after'=>self::version($s),'occurred_at'=>now()]);
    }
    public function update(int $id,User $actor,array $changes,string $expected):DoctorSchedule{
        DoctorScheduleAccess::authorize($actor);$original=DoctorSchedule::findOrFail($id);
        return DB::transaction(function()use($original,$id,$actor,$changes,$expected){
            $ids=array_unique([(int)$original->doctor_id,(int)($changes['doctor_id']??$original->doctor_id)]);
            $doctors=Doctor::whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $s=DoctorSchedule::whereKey($id)->lockForUpdate()->firstOrFail();
            DoctorScheduleAccess::authorize($actor->fresh());
            abort_unless((int)$s->doctor_id===(int)$original->doctor_id&&hash_equals(self::version($s),$expected),409,
                'El horario cambió. Actualiza el calendario antes de guardar; no se sobrescribió otra edición.');
            $target=$doctors->get((int)($changes['doctor_id']??$s->doctor_id));
            if(($changes['estado']??null)!=='INACTIVO'&&(!$target||$target->estado!=='ACTIVO'))
                throw ValidationException::withMessages(['doctor_id_edit'=>'El médico destino debe estar activo.']);
            if(isset($changes['site_id'])&&(int)$changes['site_id']!==(int)$s->site_id
                &&!\App\Models\Site::activo()->whereKey($changes['site_id'])->exists())
                throw ValidationException::withMessages(['site_id_edit'=>'La sede destino debe estar activa.']);
            $before=self::snapshot($s);$version=self::version($s);
            $candidates=Appointment::where('doctor_id',$s->doctor_id)->whereDate('fecha_cita','>=',now(config('scheduling.operational_timezone','America/Lima'))->toDateString())
                ->whereNotIn('estado_cita',['ATENDIDO','CANCELADO','NO_ASISTIO','RETIRO'])
                ->where(fn($q)=>$q->whereNull('tipo_agendamiento')->orWhere('tipo_agendamiento','<>','FUERA_HORARIO'))
                ->where(function($q){$q->whereIn('estado_agenda',['LEGADO','CONFIRMADA'])->orWhere(function($pending){
                    // Structural coverage also protects funded ADD reservations; exclusivity remains regular-only.
                    \App\Support\Scheduling\FundedReservation::apply($pending,'appointments',false);
                });})->get(['id','doctor_id','site_id','fecha_cita','hora_cita','duracion_cita']);
            $protected=$candidates->filter(fn($a)=>$this->covers($before,$a));
            $s->forceFill($changes+['lock_version'=>(int)$s->lock_version+1])->save();
            $remaining=DoctorSchedule::where('doctor_id',$before['doctor_id'])->where('estado','ACTIVO')->get()->map(fn($b)=>self::snapshot($b));
            foreach($protected as $a){
                if(!$remaining->contains(fn($block)=>$this->covers($block,$a,$before['duration'])))
                    throw ValidationException::withMessages(['horario'=>'El cambio dejaría una cita confirmada o una reserva con dinero fuera de un intervalo válido. Reprograma mediante el flujo autorizado; no se modificó el horario ni las citas.']);
            }
            $this->audit($s,$actor->id,($changes['estado']??null)==='INACTIVO'?'INACTIVATED':'UPDATED',$before,$version);
            return $s;
        },3);
    }
    private function covers(array $block,Appointment $a,?int $fallback=null):bool{
        $date=substr($a->fecha_cita,0,10);
        if($block['state']!=='ACTIVO'||$block['doctor_id']!==(int)$a->doctor_id
            ||$block['site_id']!==($a->site_id===null?null:(int)$a->site_id))return false;
        if($block['date']!==null?$block['date']!==$date:Carbon::parse($date)->dayOfWeekIso!==$block['weekday'])return false;
        $minutes=fn($time)=>(int)substr($time,0,2)*60+(int)substr($time,3,2);
        $start=$minutes($a->hora_cita);$from=$minutes($block['start']);$end=$start+AppointmentOccupancy::minutesFor($a->duracion_cita,$fallback??$block['duration']);
        return $block['duration']>0&&$start>=$from&&$end<=$minutes($block['end'])&&($start-$from)%$block['duration']===0;
    }
}
