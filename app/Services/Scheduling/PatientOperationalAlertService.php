<?php
namespace App\Services\Scheduling;
use App\Models\{User,Patient};
use App\Support\Scheduling\AdmissionRolePolicy as Policy;
use Illuminate\Support\Facades\{DB,Crypt,Validator};

final class PatientOperationalAlertService
{
    public const CATEGORIES=['communication'=>'Incidente de trato o comunicación','agreement'=>'Incumplimiento de acuerdo operativo','safety'=>'Incidente de seguridad en sede','other'=>'Otro incidente observable'];
    public function summary(int $patientId,User $actor,int $appointmentId): ?array {
        try{Policy::alertAppointment($actor,$appointmentId);}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){if(in_array($e->getStatusCode(),[403,404]))return null;throw $e;}
        $a=DB::table('patient_operational_alerts')->where('patient_id',$patientId)->where('active',true)->first();
        // No sensitive reason in the board feed, search index, DOM title or analytics.
        return $a?['active'=>true,'version'=>(int)$a->version,'url'=>url('/scheduling-mvp/admission/appointments/'.$appointmentId.'/patient-alert')]:null;
    }
    public function detail(int $id,User $actor): array {
        $appointment=Policy::alertAppointment($actor,$id);$a=DB::table('patient_operational_alerts')->where('patient_id',$appointment->patient_id)->first();
        $authorEvent=$a?DB::table('patient_operational_alert_events')->where('alert_id',$a->id)->whereIn('action',['CREATED','UPDATED'])->orderByDesc('id')->first():null;
        $events=$a?DB::table('patient_operational_alert_events')->where('alert_id',$a->id)->orderByDesc('id')->limit(30)->get():collect();
        return ['patient_id'=>(int)$appointment->patient_id,'version'=>(int)($a?->version??0),'active'=>(bool)($a?->active??false),'category'=>$a?->category,'incident_on'=>$a?->incident_on,
            'reason'=>$a?Crypt::decryptString($a->reason_ciphertext):null,'updated_at'=>$a?\Carbon\Carbon::parse($a->updated_at,config('app.timezone'))->toISOString():null,
            'reason_by'=>$authorEvent?User::find($authorEvent->actor_user_id)?->name:null,'reason_at'=>$authorEvent?\Carbon\Carbon::parse($authorEvent->occurred_at,config('app.timezone'))->toISOString():null,
            'updated_by'=>$a?User::find($a->updated_by_user_id)?->name:null,'can_edit'=>Policy::operational($actor),'can_resolve'=>$a?->active && (Policy::operational($actor)||Policy::administrator($actor)),
            'categories'=>Policy::operational($actor)?self::CATEGORIES:[],
            'history'=>$events->map(fn($e)=>['action'=>$e->action,'at'=>\Carbon\Carbon::parse($e->occurred_at,config('app.timezone'))->toISOString(),'actor'=>User::find($e->actor_user_id)?->name])->all()];
    }
    public function mutate(int $id,User $actor,array $data): void {
        Validator::make($data,['request_key'=>'required|uuid','expected_version'=>'required|integer|min:0','expected_patient_id'=>'required|integer|min:1','action'=>'required|in:save,resolve','confirmed'=>'required|accepted','facts_confirmed'=>'exclude_unless:action,save|required|accepted','category'=>'required_if:action,save|in:'.implode(',',array_keys(self::CATEGORIES)),'incident_on'=>'required_if:action,save|date_format:Y-m-d|before_or_equal:today'])->validate();
        $data['expected_version']=(int)$data['expected_version'];$resolve=$data['action']==='resolve';
        abort_unless(Policy::operational($actor)||($resolve&&Policy::administrator($actor)),403);
        $appointment=Policy::alertAppointment($actor,$id);
        abort_unless((int)$appointment->patient_id===(int)$data['expected_patient_id'],409,'El paciente asociado cambió. Actualiza antes de guardar.');
        $reason=trim($data['reason']);
        Validator::make(['reason'=>$reason],['reason'=>'required|string|min:1|max:1000'])->validate();
        abort_if(preg_match('/\b(idiota|imb[eé]cil|est[uú]pido|subnormal|esquizofr[eé]nico|psic[oó]pata|retrasado mental)\b/iu',$reason),422,'Describe hechos observables, sin insultos, clasificaciones discriminatorias ni diagnósticos.');
        $data['reason']=$reason;
        AppointmentReevaluationService::transaction(function()use($id,$actor,$data,$resolve,$appointment){
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $current=Policy::fresh($actor);
            \App\Models\Appointment::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless(Policy::operational($current)||($resolve&&Policy::administrator($current)),403);
            $lockedAppointment=Policy::alertAppointment($current,$id);
            abort_unless((int)$lockedAppointment->patient_id===(int)$data['expected_patient_id'],409,'El paciente asociado cambió. Actualiza antes de guardar.');
            $hash=hash('sha256',json_encode(['PATIENT_ALERT',$id,$data]));
            DB::table('appointment_operations')->upsert([['actor_user_id'=>$actor->id,'request_key'=>$data['request_key'],'payload_hash'=>$hash,'created_at'=>now(),'updated_at'=>now()]],['actor_user_id','request_key'],['request_key']);
            $op=DB::table('appointment_operations')->where('actor_user_id',$actor->id)->where('request_key',$data['request_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($op->payload_hash,$hash),409,'La clave corresponde a otra operación.');if($op->appointment_id)return;
            Patient::whereKey($appointment->patient_id)->lockForUpdate()->firstOrFail();
            $a=DB::table('patient_operational_alerts')->where('patient_id',$appointment->patient_id)->lockForUpdate()->first();
            abort_unless((int)($a?->version??0)===$data['expected_version'],409,'La alerta cambió. Actualiza antes de guardar.');
            abort_if($resolve&&!$a?->active,422,'No hay una alerta activa para resolver.');
            $before=$a?(array)$a:null;
            $changes=['active'=>!$resolve,'version'=>$data['expected_version']+1,'updated_by_user_id'=>$actor->id,'updated_at'=>now()];
            if(!$resolve)$changes+=['category'=>$data['category'],'incident_on'=>$data['incident_on'],'reason_ciphertext'=>Crypt::encryptString($data['reason'])];
            if($a){DB::table('patient_operational_alerts')->where('id',$a->id)->update($changes);$alertId=$a->id;}
            else $alertId=DB::table('patient_operational_alerts')->insertGetId($changes+['patient_id'=>$appointment->patient_id,'created_by_user_id'=>$actor->id,'created_at'=>now()]);
            $after=DB::table('patient_operational_alerts')->where('id',$alertId)->first();
            DB::table('patient_operational_alert_events')->insert(['alert_id'=>$alertId,'appointment_id'=>$id,'actor_user_id'=>$actor->id,'request_key'=>$data['request_key'],'action'=>$resolve?'RESOLVED':($a?'UPDATED':'CREATED'),'snapshot_ciphertext'=>Crypt::encryptString(json_encode(['before'=>$before,'after'=>$after,'reason'=>$data['reason']])),'occurred_at'=>now()]);
            DB::table('appointment_operations')->where('id',$op->id)->update(['appointment_id'=>$id,'updated_at'=>now()]);
        },3);
    }
}
