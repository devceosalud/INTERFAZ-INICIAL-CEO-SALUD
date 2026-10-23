<?php
namespace App\Services\Scheduling;
use App\Models\{Appointment,User};
use App\Support\Scheduling\{ClinicalAccess,ClinicalCapability,ProcedureCatalog};
use Illuminate\Support\Facades\{DB,Crypt};

/** Longitudinal state, independent of care, billing and completion of the original exam. */
final class MedicalProcedureFollowupService
{
    public const STATES=['PENDING'=>'Pendiente','COORDINATING'=>'En coordinación','SCHEDULED'=>'Programado','DONE'=>'Realizado','CANCELLED'=>'Descartado / cancelado'];
    public function synchronize(Appointment $a,User $actor,string $requestKey): void {
        if(DB::transactionLevel()<1)throw new \LogicException('The request and its followup must commit atomically.');
        $requests=DB::table('appointment_requested_procedures')->where('appointment_id',$a->id)->get()->keyBy('id');
        $existing=DB::table('medical_procedure_followups')->where('appointment_id',$a->id)->lockForUpdate()->get()->keyBy('requested_procedure_id');
        $clinical=$actor->hasRole('MEDICO');
        if($clinical){ClinicalAccess::actor($actor,ClinicalCapability::PROCEDURES);ClinicalAccess::appointment($actor,$a);}
        foreach($requests as $p){
            $old=$existing[$p->id]??null;$family=$p->procedure_family??(ProcedureCatalog::entries()[$p->procedure_key]['family']??'other');
            if(!$old && (!$clinical||!$p->selected||!ProcedureCatalog::requiresFollowup($family,$p->procedure_key)))continue;
            if($old){
                if((bool)$old->source_active===(bool)$p->selected)continue;
                DB::table('medical_procedure_followups')->where('id',$old->id)->update(['source_active'=>(bool)$p->selected,'version'=>$old->version+1,'updated_by_user_id'=>$actor->id,'updated_at'=>now()]);
                $this->event($old->id,$actor,$requestKey,$p->selected?'SOURCE_RESTORED':'SOURCE_WITHDRAWN',(array)$old,null);
            }else{
                $id=DB::table('medical_procedure_followups')->insertGetId(['requested_procedure_id'=>$p->id,'appointment_id'=>$a->id,'patient_id'=>$a->patient_id,'doctor_id'=>$a->doctor_id,'procedure_family'=>$family,'procedure_label'=>$p->label_snapshot??$p->custom_label??ProcedureCatalog::labels()[$p->procedure_key],
                    'requested_at'=>$p->requested_at,'source_active'=>true,'status'=>'PENDING','version'=>1,'updated_by_user_id'=>$actor->id,'created_at'=>now(),'updated_at'=>now()]);
                $this->event($id,$actor,$requestKey,'CREATED',null,null);
            }
        }
    }
    private function query(User $actor){
        $u=ClinicalAccess::actor($actor);
        return DB::table('medical_procedure_followups as f')->join('appointments as a','a.id','=','f.appointment_id')->join('patients as p','p.id','=','f.patient_id')->join('doctors as d','d.id','=','f.doctor_id')
            ->where('f.doctor_id',$u->doctor_id)->where('a.doctor_id',$u->doctor_id)->whereColumn('f.patient_id','a.patient_id')->whereIn('a.estado_agenda',['LEGADO','CONFIRMADA']);
    }
    public function feed(User $actor,array $filters): array {
        $query=$this->query($actor);
        if(!empty($filters['patient'])){
            $term='%'.addcslashes(trim($filters['patient']),'%_\\').'%';
            $query->where(fn($q)=>$q->where('p.nombre','like',$term)->orWhere('p.apellido_paterno','like',$term)->orWhere('p.apellido_materno','like',$term)->orWhere('p.historia_clinica','like',$term));
        }
        $counts=(clone $query)->select('f.status',DB::raw('COUNT(*) total'))->groupBy('f.status')->pluck('total','status');
        $families=$this->query($actor)->distinct()->pluck('f.procedure_family')->all();
        if(!empty($filters['status']))$query->where('f.status',$filters['status']);
        if(!empty($filters['procedure']))$query->where('f.procedure_family',$filters['procedure']);
        $total=(clone $query)->count();$page=$filters['page']??1;
        $rows=$query->select('f.*','a.fecha_cita','a.hora_cita','p.nombre','p.apellido_paterno','p.apellido_materno','p.historia_clinica','d.nombre as doctor_name')->orderBy('f.requested_at',($filters['order']??'oldest')==='newest'?'desc':'asc')->orderBy('f.id')->skip(($page-1)*25)->take(25)->get();
        return ['can_update'=>ClinicalAccess::has($actor,ClinicalCapability::PROCEDURES),'rows'=>$rows->map(fn($f)=>$this->payload($f))->all(),'total'=>$total,'page'=>$page,'counts'=>collect(self::STATES)->map(fn($l,$key)=>(int)($counts[$key]??0))->all(),'states'=>self::STATES,'procedures'=>array_intersect_key(ProcedureCatalog::FAMILIES,array_flip($families))];
    }
    private function payload(object $f): array {
        return ['id'=>$f->id,'version'=>(int)$f->version,'patient'=>trim($f->apellido_paterno.' '.$f->apellido_materno.' '.$f->nombre),'history'=>$f->historia_clinica,
            'procedure'=>$f->procedure_label,'family'=>$f->procedure_family,'appointment_date'=>substr($f->fecha_cita,0,10),'requested_at'=>\Carbon\Carbon::parse($f->requested_at,config('app.timezone'))->toISOString(),'doctor'=>$f->doctor_name,'status'=>$f->status,'source_active'=>(bool)$f->source_active,'planned_review_on'=>$f->planned_review_on,
            'updated_at'=>\Carbon\Carbon::parse($f->updated_at,config('app.timezone'))->toISOString(),'notes'=>$f->notes_ciphertext?Crypt::decryptString($f->notes_ciphertext):'',
            'appointment_url'=>route('scheduling.mvp.admission',['fecha'=>substr($f->fecha_cita,0,10),'doctor_id'=>$f->doctor_id,'appointment_id'=>$f->appointment_id,'focus'=>1])];
    }
    public function detail(User $actor,int $id): array {
        $f=$this->query($actor)->where('f.id',$id)->select('f.*','a.fecha_cita','p.nombre','p.apellido_paterno','p.apellido_materno','p.historia_clinica','d.nombre as doctor_name')->first();
        abort_unless($f,404);
        $events=DB::table('medical_procedure_followup_events')->where('followup_id',$id)->orderByDesc('id')->limit(50)->get();
        return $this->payload($f)+['can_update'=>ClinicalAccess::has($actor,ClinicalCapability::PROCEDURES),'events'=>$events->map(fn($e)=>['action'=>$e->action,'at'=>$e->occurred_at,'actor'=>User::find($e->actor_user_id)?->name,'snapshot'=>json_decode(Crypt::decryptString($e->snapshot_ciphertext),true)])->all()];
    }
    public function update(User $actor,int $id,array $data): void {
        ClinicalAccess::actor($actor,ClinicalCapability::PROCEDURES);
        \Illuminate\Support\Facades\Validator::make($data,['request_key'=>'required|uuid','expected_version'=>'required|integer|min:1','status'=>'required|in:'.implode(',',array_keys(self::STATES)),'planned_review_on'=>'nullable|required_if:status,SCHEDULED|date_format:Y-m-d','notes'=>'nullable|string|max:1000','reason'=>'required|string|min:15|max:1000','confirmed'=>'required|accepted'])->validate();
        $data['expected_version']=(int)$data['expected_version'];
        $original=$this->query($actor)->where('f.id',$id)->select('f.appointment_id')->first();
        abort_unless($original,404);
        AppointmentReevaluationService::transaction(function()use($actor,$id,$data,$original){
            // Match the procedure request's operation -> clinical/appointment -> followup lock order.
            $hash=hash('sha256',json_encode(['MEDICAL_FOLLOWUP',$id,$data]));
            DB::table('appointment_operations')->upsert([['actor_user_id'=>$actor->id,'request_key'=>$data['request_key'],'payload_hash'=>$hash,'created_at'=>now(),'updated_at'=>now()]],['actor_user_id','request_key'],['request_key']);
            $op=DB::table('appointment_operations')->where('actor_user_id',$actor->id)->where('request_key',$data['request_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($op->payload_hash,$hash),409,'La clave corresponde a otra operación.');
            [$u,$a]=ClinicalAccess::lock($actor,$original->appointment_id,ClinicalCapability::PROCEDURES);
            $f=DB::table('medical_procedure_followups')->where('id',$id)->where('doctor_id',$u->doctor_id)->lockForUpdate()->first();
            abort_unless($f,404);abort_unless((int)$f->patient_id===(int)$a->patient_id,409,'La vinculación del paciente cambió. Actualiza antes de continuar.');if($op->appointment_id)return;
            abort_unless((int)$f->version===$data['expected_version'],409,'El seguimiento cambió. Actualiza antes de guardar.');
            abort_if(!$f->source_active&&in_array($data['status'],['COORDINATING','SCHEDULED'],true),422,'La solicitud fue retirada. Revisa su trazabilidad antes de coordinarla.');
            DB::table('medical_procedure_followups')->where('id',$id)->update(['status'=>$data['status'],'planned_review_on'=>$data['planned_review_on']??null,'notes_ciphertext'=>Crypt::encryptString(trim($data['notes']??'')),'version'=>$f->version+1,'updated_by_user_id'=>$u->id,'updated_at'=>now()]);
            $this->event($id,$u,$data['request_key'],'UPDATED',(array)$f,trim($data['reason']));
            DB::table('appointment_operations')->where('id',$op->id)->update(['appointment_id'=>$a->id,'updated_at'=>now()]);
        },3);
    }
    private function event(int $id,User $actor,string $key,string $action,?array $before,?string $reason): void {
        $after=(array)DB::table('medical_procedure_followups')->where('id',$id)->first();
        DB::table('medical_procedure_followup_events')->insert(['followup_id'=>$id,'actor_user_id'=>$actor->id,'request_key'=>$key,'action'=>$action,'snapshot_ciphertext'=>Crypt::encryptString(json_encode(['before'=>$before,'after'=>$after,'reason'=>$reason])),'occurred_at'=>now()]);
    }
}
