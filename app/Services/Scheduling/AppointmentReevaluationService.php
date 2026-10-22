<?php
namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Read-only union of independent return-to-doctor decisions; never migrates or invents evidence. */
final class AppointmentReevaluationService
{
    public const MEDICAL_INDICATED = 'REEVALUACION_MEDICA_INDICADA';
    public const MEDICAL_WITHDRAWN = 'REEVALUACION_MEDICA_RETIRADA';

    /** Read the latest physician decision. Ordinary operational marks remain separate. */
    public static function loadIndications(\Illuminate\Support\Collection $appointments): void
    {
        if ($appointments->isEmpty()) return;
        $latest = \App\Models\AppointmentEvent::query()->whereIn('id', function ($q) use ($appointments) {
            $q->from('appointment_events')->selectRaw('MAX(id)')
                ->whereIn('appointment_id', $appointments->pluck('id'))
                ->whereIn('event_type', [self::MEDICAL_INDICATED, self::MEDICAL_WITHDRAWN])
                ->groupBy('appointment_id');
        })->get()->keyBy('appointment_id');
        foreach ($appointments as $a) $a->setRelation('medicalReevaluationEvent', $latest->get($a->id));
    }

    public static function indicated(Appointment $a): bool
    {
        $event = $a->relationLoaded('medicalReevaluationEvent')
            ? $a->getRelation('medicalReevaluationEvent')
            : $a->events()->whereIn('event_type',[self::MEDICAL_INDICATED,self::MEDICAL_WITHDRAWN])->orderByDesc('id')->first();
        return $event?->event_type === self::MEDICAL_INDICATED;
    }

    public static function pending(Appointment $a): bool
    {
        return $a->estado_cita === 'EN_ATENCION' && self::indicated($a) && !self::ready($a);
    }

    /** Compatibility: existing medical account requires its own session;
        an unlinked doctor keeps the old explicit operational workflow. */
    public static function linkedPhysicianExists(Appointment $a): bool
    {
        return \Illuminate\Support\Facades\Schema::hasColumn('users','doctor_id')
            && User::query()->where('doctor_id',$a->doctor_id)->where('is_active',true)
                ->whereHas('roles',fn($r)=>$r->where('name','MEDICO')->where('guard_name','web'))
                ->exists();
    }

    /** No new permission: requires an active MEDICO role, existing Agenda access,
        and a verified doctor_id already linked to the authenticated user. */
    public static function physicianCanAct(User $actor, Appointment $a): bool
    {
        if (!$actor->is_active || !$actor->hasRole('MEDICO')
            || !$actor->can(\App\Support\Scheduling\SchedulingCapability::MVP_ACCESS)
            || !$actor->can(\App\Support\Scheduling\SchedulingCapability::VIEW)
            || !\Illuminate\Support\Facades\Schema::hasColumn('users','doctor_id')) return false;
        $linked=(int)DB::table('users')->where('id',$actor->id)->value('doctor_id');
        return $linked>0 && $linked===(int)$a->doctor_id
            && \App\Models\Doctor::whereKey($linked)->where('estado','ACTIVO')->exists();
    }

    /** Records the physician's own clinical instruction in the existing event ledger.
        Every write is bound to a doctor, an unchanged appointment and a replay key. */
    public function physicianDecision(int $id, User $actor, array $data): Appointment
    {
        $original=Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->firstOr(fn()=>abort(404));
        abort_unless(self::physicianCanAct($actor,$original),403);
        $decision=$data['action'];
        $payloadHash=hash('sha256',json_encode(['MEDICAL_REEVALUATION_V1',$id,$decision,$data]));
        return DB::transaction(function()use($id,$actor,$original,$data,$decision,$payloadHash){
            DB::table('appointment_operations')->upsert([[
                'actor_user_id'=>$actor->id,'request_key'=>$data['request_key'],
                'payload_hash'=>$payloadHash,'created_at'=>now(),'updated_at'=>now(),
            ]],['actor_user_id','request_key'],['request_key']);
            $op=DB::table('appointment_operations')->where('actor_user_id',$actor->id)
                ->where('request_key',$data['request_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($op->payload_hash,$payloadHash),409,'Operación duplicada con datos distintos.');
            if ($op->appointment_id) return Appointment::visibleToAgendaUser($actor->id)
                ->whereKey($op->appointment_id)->firstOr(fn()=>abort(404));
            \App\Models\Doctor::whereKey($original->doctor_id)->lockForUpdate()->firstOrFail();
            $a=Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->lockForUpdate()->firstOr(fn()=>abort(404));
            abort_unless(self::physicianCanAct($actor,$a),403);
            abort_unless((int)$original->doctor_id===(int)$a->doctor_id
                && $a->estado_cita==='EN_ATENCION'
                && $data['expected_state']==='EN_ATENCION'
                && $a->hora_atencion && !$a->hora_atendido && !$a->hora_salida
                && in_array($a->estado_agenda,['LEGADO','CONFIRMADA'],true),409,'La atención cambió. Actualiza la cita.');
            $latest=$a->events()->whereIn('event_type',[self::MEDICAL_INDICATED,self::MEDICAL_WITHDRAWN])
                ->orderByDesc('id')->lockForUpdate()->first();
            $active=$latest?->event_type===self::MEDICAL_INDICATED;
            $a->setRelation('medicalReevaluationEvent',$latest);
            $a->loadMissing('operationalReview');
            app(ManualReevaluationService::class)->load(collect([$a]));
            abort_if($decision==='indicate' && $active,422,'La reevaluación ya está indicada.');
            abort_if($decision==='withdraw' && !$active,422,'No hay indicación activa.');
            if($decision==='withdraw'){
                abort_if(self::ready($a),422,'La reevaluación ya está lista.');
                abort_unless(mb_strlen(trim($data['reason']??''))>=3,422,'Indica el motivo de retirar la reevaluación.');
            }
            $event=app(AppointmentHistory::class)->record($a,$decision==='indicate'
                ? self::MEDICAL_INDICATED:self::MEDICAL_WITHDRAWN,$actor->id,
                ['motivo'=>$decision==='withdraw'?trim($data['reason']):null,
                 'metadata'=>['source'=>'VERIFIED_PHYSICIAN_SAME_APPOINTMENT_V1',
                     'doctor_id'=>(int)$a->doctor_id,'previous_event_id'=>$latest?->id,'operation_id'=>$op->id]]);
            DB::table('appointment_operations')->where('id',$op->id)
                ->update(['appointment_id'=>$a->id,'updated_at'=>now()]);
            $a->setRelation('medicalReevaluationEvent',$event);
            return $a;
        },3);
    }

    public static function directReady(Appointment $a): bool
    {
        return $a->relationLoaded('manualReevaluationEvent')
            && $a->getRelation('manualReevaluationEvent')?->event_type === ManualReevaluationService::MARKED
            && ($a->estado_cita === 'ATENDIDO' || ($a->estado_cita === 'EN_ATENCION' && self::indicated($a))) && !$a->hora_salida
            && in_array($a->estado_agenda, ['LEGADO','CONFIRMADA'], true);
    }

    public static function ready(Appointment $a): bool
    {
        // Once the verified physician has FINISHED this clinical episode,
        // it is no longer waiting for a return to the consultorio.
        // Preserve the legacy manual direct return for ATENDIDO episodes
        // that never had a new medical indication.
        if ($a->estado_cita==='ATENDIDO' && self::indicated($a)) return false;
        return self::directReady($a) || OperationalProcedureService::ready($a);
    }

    public static function summary(Appointment $a): array
    {
        $origins=[];
        if (self::directReady($a)) $origins[]='MANUAL_DIRECT';
        if (OperationalProcedureService::ready($a)) {
            $origins[]=$a->operationalReview->origin === 'MANUAL' ? 'MANUAL_PARTIAL' : 'AUTOMATIC';
        }
        return ['ready'=>count($origins)>0,'pending'=>$a->estado_cita==='EN_ATENCION' && self::indicated($a) && count($origins)===0,
            'indicated'=>self::indicated($a),'origins'=>$origins];
    }

    public function detail(Appointment $a, User $actor, array $procedures): array
    {
        $a->loadMissing('operationalReview.decidedBy');
        if (!$a->relationLoaded('manualReevaluationEvent')) app(ManualReevaluationService::class)->load(collect([$a]));
        $manual=app(ManualReevaluationService::class);
        $direct=$manual->state($a)+['active'=>self::directReady($a),
            'can_update'=>$manual->canUpdate($actor)&&$manual->eligible($a)];
        $history=$this->history($a->id,$actor);
        return self::summary($a)+['direct'=>$direct,'procedural'=>$procedures['review'],
            'history'=>$history['rows'],'history_meta'=>array_diff_key($history,['rows'=>true])];
    }

    /** Stable append-only snapshot: later actions cannot shift or duplicate the next audit page. */
    public function history(int $id, User $actor, int $page=1, ?array $cursor=null): array
    {
        Appointment::visibleToOperationalUser($actor)->whereKey($id)->firstOr(fn()=>abort(404));
        $cursor??=['direct_until'=>(int)(DB::table('appointment_events')->where('appointment_id',$id)
            ->whereIn('event_type',[ManualReevaluationService::MARKED,ManualReevaluationService::CLEARED])->max('id')??0),
            'procedure_until'=>(int)(DB::table('appointment_procedure_events')->where('appointment_id',$id)->max('id')??0)];
        $direct=DB::table('appointment_events as e')->leftJoin('users as u','u.id','=','e.actor_user_id')
            ->where('e.appointment_id',$id)->where('e.id','<=',$cursor['direct_until'])
            ->whereIn('e.event_type',[ManualReevaluationService::MARKED,ManualReevaluationService::CLEARED])
            ->select(['e.id','e.event_type as action','e.occurred_at as at','e.actor_user_id','u.name',DB::raw("'MANUAL_DIRECT' as source"),DB::raw('NULL as snapshot')]);
        $structured=DB::table('appointment_procedure_events as e')->leftJoin('users as u','u.id','=','e.actor_user_id')
            ->where('e.appointment_id',$id)->where('e.id','<=',$cursor['procedure_until'])
            ->select(['e.id','e.action','e.occurred_at as at','e.actor_user_id','u.name',DB::raw("'PROCEDURES' as source"),'e.after as snapshot']);
        $rows=DB::query()->fromSub($direct->unionAll($structured),'audit')->orderByDesc('at')->orderByDesc('source')->orderByDesc('id')
            ->offset(($page-1)*40)->limit(41)->get();
        return ['page'=>$page,'has_more'=>$rows->count()>40,'cursor'=>$cursor,'rows'=>$rows->take(40)->map(function($e){
            $entry=['id'=>($e->source==='MANUAL_DIRECT'?'event-':'procedure-').$e->id,'source'=>$e->source,'action'=>$e->action,
                'at'=>Carbon::parse($e->at,config('app.timezone'))->toISOString(),
                'actor'=>['id'=>(int)$e->actor_user_id,'name'=>$e->name??'Usuario no disponible']];
            if($e->source==='PROCEDURES'){
                $review=json_decode($e->snapshot,true)['review']??[];
                $entry+=['requested'=>(int)($review['requested_count']??0),'completed'=>(int)($review['completed_count']??0),
                    'origin'=>$review['origin']??null,'status'=>$review['status']??null,'reason'=>$review['reason']??null];
            }
            return $entry;
        })->values()->all()];
    }

    public function queue(User $actor): array
    {
        $appointments=Appointment::visibleToOperationalUser($actor)
            ->whereDate('fecha_cita',now('America/Lima')->toDateString())
            ->where(function($q){
                $q->whereHas('operationalReview',fn($r)=>$r->whereNotNull('ready_at')->orWhere('requested_count','>',0))
                    ->orWhereHas('events',fn($e)=>$e->whereIn('event_type',[ManualReevaluationService::MARKED,ManualReevaluationService::CLEARED]));
            })->select(['id','patient_id','doctor_id','service_id','fecha_cita','hora_cita','estado_agenda','estado_cita','hora_salida'])
            ->with(['operationalReview.decidedBy:id,name','patient:id,nombre,apellido_paterno,apellido_materno','doctor:id,nombre','service:id,nombre'])->get();
        app(ManualReevaluationService::class)->load($appointments);
        $manual=app(ManualReevaluationService::class);
        return $appointments->map(function($a)use($actor,$manual){
            $procedures=app(OperationalProcedureService::class)->detail($a,$actor);
            $summary=self::summary($a);$direct=$manual->state($a);$review=$procedures['review'];
            $activeTimes=[];
            if(self::directReady($a)) $activeTimes[]=$direct['at'];
            if(OperationalProcedureService::ready($a)) $activeTimes[]=$review['ready_at'];
            sort($activeTimes);
            $status=$summary['ready']?'READY':$review['status'];
            if(!$summary['ready'] && !$procedures['requested_count'] && $direct['version']) {
                $status=$direct['marked']?'INACTIVE':'WITHDRAWN';
            }
            $review['status']=$status;
            if(self::directReady($a)) {
                $review['origin']='MANUAL_DIRECT';$review['authorized_by']=$direct['actor'];
            }
            $review['ready_at']=$activeTimes[0]??$review['ready_at']??$direct['at'];
            unset($review['reason']);
            return ['id'=>$a->id,'patient'=>trim($a->patient?->apellido_paterno.' '.$a->patient?->apellido_materno.' '.$a->patient?->nombre),
                'doctor'=>$a->doctor?->nombre,'service'=>$a->service?->nombre,'appointment_time'=>substr($a->hora_cita,0,5),
                'review'=>$review,'origins'=>$summary['origins'],'procedures'=>$procedures['items'],
                'requested_count'=>$procedures['requested_count'],'completed_count'=>$procedures['completed_count'],
                'pending_count'=>$procedures['pending_count']];
        })->sortBy(fn($row)=>($row['review']['ready_at']??'').'|'.str_pad((string)$row['id'],20,'0',STR_PAD_LEFT))->values()->all();
    }
}
