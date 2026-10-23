<?php
namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Read-only union of independent return-to-doctor decisions; never migrates or invents evidence. */
final class AppointmentReevaluationService
{
    /** MariaDB snapshot isolation may reject a current read with error 1020.
        Retry the complete, rolled-back operation with a fresh snapshot, never
        continue a partial clinical decision or acknowledge an uncommitted write. */
    public static function transaction(\Closure $callback,int $attempts=3)
    {
        $outer=DB::transactionLevel();
        for($try=1;;$try++){
            try{return DB::transaction($callback,$attempts);}
            catch(\Illuminate\Database\QueryException $e){
                if((int)($e->errorInfo[1]??0)!==1020 || $outer>0 || $try>=$attempts)throw $e;
            }
        }
    }
    // Historical aliases keep imported audit readers compatible; no second state machine.
    public const INDICATED = self::MEDICAL_INDICATED;
    public const WITHDRAWN = self::MEDICAL_WITHDRAWN;
    public const MEDICAL_INDICATED = 'REEVALUACION_MEDICA_INDICADA';
    public const MEDICAL_WITHDRAWN = 'REEVALUACION_MEDICA_RETIRADA';
    public const OPERATIONAL_CONFIRMED = 'REEVALUACION_OPERATIVA_CONFIRMADA';
    public const MEDICAL_RESUMED = 'REEVALUACION_MEDICA_RETOMADA';
    public const PATIENT_DECLINED = 'REEVALUACION_RECHAZADA_POR_PACIENTE';

    /** Load the latest events in batches: every state belongs to the SAME appointment. */
    public static function loadIndications(\Illuminate\Support\Collection $appointments): void
    {
        if ($appointments->isEmpty()) return;
        foreach ([
            'medicalReevaluationEvent' => [self::MEDICAL_INDICATED, self::MEDICAL_WITHDRAWN],
            'operationalConfirmationEvent' => [self::OPERATIONAL_CONFIRMED],
            'medicalReturnEvent' => [self::MEDICAL_RESUMED],
            'reevaluationDeclinedEvent' => [self::PATIENT_DECLINED],
        ] as $relation => $types) {
            // MySQL does not make a MAX(id) subquery a current read merely
            // because the OUTER SELECT has FOR UPDATE. Avoid that snapshot
            // trap inside a write transaction after waiting on the appointment.
            if(DB::transactionLevel()>0){
                foreach($appointments as $a){
                    $events=\App\Models\AppointmentEvent::where('appointment_id',$a->id)->lockForUpdate()->get();
                    $a->setRelation($relation,$events->whereIn('event_type',$types)->sortByDesc('id')->first());
                }
                continue;
            }
            $latest = \App\Models\AppointmentEvent::query()->whereIn('id', function ($q) use ($appointments, $types) {
                $q->from('appointment_events')->selectRaw('MAX(id)')
                    ->whereIn('appointment_id', $appointments->pluck('id'))
                    ->whereIn('event_type', $types)->groupBy('appointment_id');
            })->when(DB::transactionLevel()>0,fn($q)=>$q->lockForUpdate())->get()->keyBy('appointment_id');
            foreach ($appointments as $a) $a->setRelation($relation, $latest->get($a->id));
        }
    }

    private static function event(Appointment $a, string $relation, array $types): ?\App\Models\AppointmentEvent
    {
        return $a->relationLoaded($relation) ? $a->getRelation($relation)
            : $a->events()->whereIn('event_type', $types)->orderByDesc('id')->when(DB::transactionLevel()>0,fn($q)=>$q->lockForUpdate())->first();
    }

    private static function indication(Appointment $a): ?\App\Models\AppointmentEvent
    {
        return self::event($a, 'medicalReevaluationEvent', [self::MEDICAL_INDICATED, self::MEDICAL_WITHDRAWN]);
    }

    public static function indicated(Appointment $a): bool
    {
        return self::indication($a)?->event_type === self::MEDICAL_INDICATED;
    }

    public static function confirmed(Appointment $a): bool
    {
        if (!self::indicated($a)) return false;
        $event = self::event($a, 'operationalConfirmationEvent', [self::OPERATIONAL_CONFIRMED]);
        return $event && $event->id > self::indication($a)->id;
    }

    public static function resumed(Appointment $a): bool
    {
        if (!self::confirmed($a)) return false;
        $event = self::event($a, 'medicalReturnEvent', [self::MEDICAL_RESUMED]);
        $confirm = self::event($a, 'operationalConfirmationEvent', [self::OPERATIONAL_CONFIRMED]);
        return $event && $event->id > $confirm->id;
    }

    public static function prePending(Appointment $a): bool
    {
        return $a->estado_cita === 'EN_ATENCION' && self::indicated($a) && !self::confirmed($a);
    }

    public static function pending(Appointment $a): bool
    {
        return $a->estado_cita === 'EN_ATENCION' && self::confirmed($a)
            && !self::ready($a) && !self::resumed($a);
    }

    public static function declined(Appointment $a): bool
    {
        $event=self::event($a,'reevaluationDeclinedEvent',[self::PATIENT_DECLINED]);
        $indication=self::indication($a);
        return $a->estado_cita==='ATENDIDO' && $event !== null && $indication !== null
            && $event->id > $indication->id;
    }

    public static function operatorCanDecline(User $actor, Appointment $a): bool
    {
        return self::operatorCanConfirm($actor)
            && ($a->attendance_mode ?? 'PRESENCIAL') === 'PRESENCIAL'
            && $a->estado_cita === 'EN_ATENCION'
            && in_array($a->estado_agenda,['LEGADO','CONFIRMADA'],true)
            && $a->hora_atencion && !$a->hora_atendido && !$a->hora_salida
            && self::indicated($a) && !self::resumed($a);
    }

    public static function operatorCanConfirm(User $actor): bool
    {
        return (bool) $actor->is_active && $actor->hasAnyRole(['ADMISION', 'COMERCIAL'])
            && $actor->can(\App\Support\Scheduling\SchedulingCapability::MVP_ACCESS)
            && $actor->can(\App\Support\Scheduling\SchedulingCapability::VIEW);
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
        try {
            $u=\App\Support\Scheduling\ClinicalAccess::actor($actor);
            \App\Support\Scheduling\ClinicalAccess::appointment($u,$a);
            return true;
        }catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){if($e->getStatusCode()!==403)throw $e;return false;}
    }

    /** Records the physician's own clinical instruction in the existing event ledger.
        Every write is bound to a doctor, an unchanged appointment and a replay key. */
    public function physicianDecision(int $id, User $actor, array $data): Appointment
    {
        abort_unless(($data['confirmed']??false),422,'Confirma expresamente la actuación.');
        $original=Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->firstOr(fn()=>abort(404));
        abort_unless(self::physicianCanAct($actor,$original),403);
        $decision=$data['action'];
        $payloadHash=hash('sha256',json_encode(['MEDICAL_REEVALUATION_V1',$id,$decision,$data]));
        return self::transaction(function()use($id,$actor,$original,$data,$decision,$payloadHash){
            DB::table('appointment_operations')->upsert([[
                'actor_user_id'=>$actor->id,'request_key'=>$data['request_key'],
                'payload_hash'=>$payloadHash,'created_at'=>now(),'updated_at'=>now(),
            ]],['actor_user_id','request_key'],['request_key']);
            $op=DB::table('appointment_operations')->where('actor_user_id',$actor->id)
                ->where('request_key',$data['request_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($op->payload_hash,$payloadHash),409,'Operación duplicada con datos distintos.');
            if ($op->appointment_id) return Appointment::visibleToAgendaUser($actor->id)
                ->whereKey($op->appointment_id)->firstOr(fn()=>abort(404));
            $cap=match($decision){'indicate'=>\App\Support\Scheduling\ClinicalCapability::INDICATE,
                'withdraw'=>\App\Support\Scheduling\ClinicalCapability::WITHDRAW, 'resume'=>\App\Support\Scheduling\ClinicalCapability::START};
            [$actor,$locked]=\App\Support\Scheduling\ClinicalAccess::lock($actor,$id,$cap);
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
            $a->setRelation('operationalReview',$a->operationalReview()->lockForUpdate()->first());
            abort_if($decision==='indicate' && $active,422,'La reevaluación ya está indicada.');
            abort_if($decision==='resume' && (!self::ready($a) || self::resumed($a)),422,
                'El retorno debe estar confirmado y listo antes de iniciar la reevaluación.');
            abort_if($decision==='withdraw' && !$active,422,'No hay indicación activa.');
            abort_if($decision==='withdraw' && self::resumed($a),422,'El médico ya retomó la atención.');
            if($decision==='withdraw'){
                abort_if(self::ready($a),422,'La reevaluación ya está lista.');
                abort_unless(mb_strlen(trim($data['reason']??''))>=3,422,'Indica el motivo de retirar la reevaluación.');
            }
            $eventType=match($decision){
                'indicate'=>self::MEDICAL_INDICATED,
                'withdraw'=>self::MEDICAL_WITHDRAWN,
                'resume'=>self::MEDICAL_RESUMED,
            };
            $event=app(AppointmentHistory::class)->record($a,$eventType,$actor->id,
                ['motivo'=>$decision==='withdraw'?trim($data['reason']):null,
                 'metadata'=>['source'=>'VERIFIED_PHYSICIAN_SAME_APPOINTMENT_V1',
                     'doctor_id'=>(int)$a->doctor_id,'previous_event_id'=>$latest?->id,'operation_id'=>$op->id]]);
            DB::table('appointment_operations')->where('id',$op->id)
                ->update(['appointment_id'=>$a->id,'updated_at'=>now()]);
            if($decision==='resume') $a->setRelation('medicalReturnEvent',$event);
            else $a->setRelation('medicalReevaluationEvent',$event);
            return $a;
        },3);
    }

    /** Reception / commercial confirms the physician's indication, without marking exams done. */
    public function confirmOperational(int $id, User $actor, array $data): Appointment
    {
        abort_unless(($data['confirmed']??false),422,'Confirma expresamente la actuación.');
        abort_unless(self::operatorCanConfirm($actor),403);
        $original=Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->firstOr(fn()=>abort(404));
        $hash=hash('sha256',json_encode(['OPERATIVE_CONFIRMATION_V1',$id,$data]));
        return self::transaction(function () use ($id,$actor,$original,$data,$hash) {
            DB::table('appointment_operations')->upsert([[
                'actor_user_id'=>$actor->id,'request_key'=>$data['request_key'],'payload_hash'=>$hash,
                'created_at'=>now(),'updated_at'=>now(),
            ]],['actor_user_id','request_key'],['request_key']);
            $op=DB::table('appointment_operations')->where('actor_user_id',$actor->id)
                ->where('request_key',$data['request_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($op->payload_hash,$hash),409,'Operación duplicada con datos distintos.');
            if($op->appointment_id) return Appointment::visibleToAgendaUser($actor->id)
                ->whereKey($op->appointment_id)->firstOr(fn()=>abort(404));
            $fresh=User::whereKey($actor->id)->lockForUpdate()->firstOr(fn()=>abort(403));
            abort_unless($fresh->auth_version===$actor->auth_version && self::operatorCanConfirm($fresh),403);
            $actor=$fresh;
            \App\Models\Doctor::whereKey($original->doctor_id)->lockForUpdate()->firstOrFail();
            $a=Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->lockForUpdate()->firstOr(fn()=>abort(404));
            abort_unless((int)$a->doctor_id===(int)$original->doctor_id
                && $a->estado_cita==='EN_ATENCION' && $data['expected_state']==='EN_ATENCION'
                && $a->hora_atencion && !$a->hora_atendido && !$a->hora_salida
                && in_array($a->estado_agenda,['LEGADO','CONFIRMADA'],true),409,'La atención cambió. Actualiza la cita.');
            app(ManualReevaluationService::class)->load(collect([$a]));
            $a->setRelation('operationalReview',$a->operationalReview()->lockForUpdate()->first());
            abort_unless(self::indicated($a) && !self::confirmed($a),422,
                'Solo puedes confirmar una pre-reevaluación médica que aún no esté confirmada.');
            $event=app(AppointmentHistory::class)->record($a,self::OPERATIONAL_CONFIRMED,$actor->id,[
                'metadata'=>['source'=>'OPERATIVE_CONFIRMATION_V1',
                    'indication_event_id'=>self::indication($a)->id,'operation_id'=>$op->id]]);
            DB::table('appointment_operations')->where('id',$op->id)
                ->update(['appointment_id'=>$a->id,'updated_at'=>now()]);
            $a->setRelation('operationalConfirmationEvent',$event);
            return $a;
        },3);
    }

    /**
     * Patient declines the return. This is an administrative closure of the
     * first visit, NOT medical reevaluation, NOT completion of pending exams.
     */
    public function closeDeclined(int $id, User $actor, array $data): Appointment
    {
        abort_unless(($data['confirmed']??false),422,'Confirma expresamente la actuación.');
        abort_unless(self::operatorCanConfirm($actor),403);
        abort_unless(mb_strlen(trim($data['reason']))>=3,422,'Indica por qué el paciente no regresará.');
        $original=Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->firstOr(fn()=>abort(404));
        $hash=hash('sha256',json_encode(['PATIENT_DECLINED_REEVALUATION_V1',$id,$data]));
        return self::transaction(function () use ($id,$actor,$original,$data,$hash) {
            DB::table('appointment_operations')->upsert([[
                'actor_user_id'=>$actor->id,'request_key'=>$data['request_key'],'payload_hash'=>$hash,
                'created_at'=>now(),'updated_at'=>now(),
            ]],['actor_user_id','request_key'],['request_key']);
            $op=DB::table('appointment_operations')->where('actor_user_id',$actor->id)
                ->where('request_key',$data['request_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($op->payload_hash,$hash),409,'Operación duplicada con datos distintos.');
            if($op->appointment_id) return Appointment::visibleToAgendaUser($actor->id)
                ->whereKey($op->appointment_id)->firstOr(fn()=>abort(404));
            $fresh=User::whereKey($actor->id)->lockForUpdate()->firstOr(fn()=>abort(403));
            abort_unless($fresh->auth_version===$actor->auth_version && self::operatorCanConfirm($fresh),403);
            $actor=$fresh;
            \App\Models\Doctor::whereKey($original->doctor_id)->lockForUpdate()->firstOrFail();
            $a=Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->lockForUpdate()->firstOr(fn()=>abort(404));
            abort_unless((int)$original->doctor_id===(int)$a->doctor_id
                && $data['expected_state']==='EN_ATENCION',409,'La cita cambió. Actualiza.');
            app(ManualReevaluationService::class)->load(collect([$a]));
            $a->setRelation('operationalReview',$a->operationalReview()->lockForUpdate()->first());
            abort_unless(self::operatorCanDecline($actor,$a),422,
                'Solo se permite cerrar una reevaluación indicada y todavía no retomada por el médico.');
            $a->forceFill(['estado_cita'=>'ATENDIDO','hora_atendido'=>now(),
                'updated_by_user_id'=>$actor->id])->save();
            $event=app(AppointmentHistory::class)->record($a,self::PATIENT_DECLINED,$actor->id,[
                'motivo'=>trim($data['reason']),
                'metadata'=>['source'=>'DECLINED_RETURN_ADMINISTRATIVE_CLOSE_V1',
                    'indication_event_id'=>self::indication($a)->id,
                    'operation_id'=>$op->id,'from'=>'EN_ATENCION','to'=>'ATENDIDO',
                    'clinical_reevaluation_performed'=>false,'exams_verified_by_this_action'=>false]]);
            DB::table('appointment_operations')->where('id',$op->id)
                ->update(['appointment_id'=>$a->id,'updated_at'=>now()]);
            $a->setRelation('reevaluationDeclinedEvent',$event);
            return $a;
        },3);
    }

    public static function directReady(Appointment $a): bool
    {
        $event=self::event($a,'manualReevaluationEvent',[ManualReevaluationService::MARKED,ManualReevaluationService::CLEARED]);
        if ($event?->event_type !== ManualReevaluationService::MARKED || $a->hora_salida
            || !in_array($a->estado_agenda,['LEGADO','CONFIRMADA'],true)) return false;
        if ($a->estado_cita==='ATENDIDO') return !self::indicated($a);
        return $a->estado_cita==='EN_ATENCION' && self::confirmed($a)
            && $event->id > self::indication($a)->id;
    }

    public static function ready(Appointment $a): bool
    {
        if (self::indicated($a)) {
            return $a->estado_cita==='EN_ATENCION' && self::confirmed($a) && !self::resumed($a)
                && (self::directReady($a) || OperationalProcedureService::ready($a));
        }
        return self::directReady($a) || OperationalProcedureService::ready($a);
    }

    public static function summary(Appointment $a): array
    {
        $ready=self::ready($a);
        $origins=[];
        if ($ready && self::directReady($a)) $origins[]='MANUAL_DIRECT';
        if ($ready && OperationalProcedureService::ready($a)) {
            $origins[]=$a->operationalReview->origin === 'MANUAL' ? 'MANUAL_PARTIAL' : 'AUTOMATIC';
        }
        return ['ready'=>$ready,'declined'=>self::declined($a),'pre_pending'=>self::prePending($a),
            'pending'=>self::pending($a),'confirmed'=>self::confirmed($a),
            'resumed'=>self::resumed($a),'indicated'=>self::indicated($a),'origins'=>$origins];
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
        $types=[ManualReevaluationService::MARKED,ManualReevaluationService::CLEARED,
            self::MEDICAL_INDICATED,self::MEDICAL_WITHDRAWN,self::OPERATIONAL_CONFIRMED,
            self::MEDICAL_RESUMED,self::PATIENT_DECLINED];
        $cursor??=['direct_until'=>(int)(DB::table('appointment_events')->where('appointment_id',$id)
            ->whereIn('event_type',$types)->max('id')??0),
            'procedure_until'=>(int)(DB::table('appointment_procedure_events')->where('appointment_id',$id)->max('id')??0)];
        $direct=DB::table('appointment_events as e')->leftJoin('users as u','u.id','=','e.actor_user_id')
            ->where('e.appointment_id',$id)->where('e.id','<=',$cursor['direct_until'])
            ->whereIn('e.event_type',$types)
            ->select(['e.id','e.event_type as action','e.occurred_at as at','e.actor_user_id','u.name',DB::raw("'MANUAL_DIRECT' as source"),'e.motivo as snapshot']);
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
            }else $entry['reason']=$e->snapshot;
            return $entry;
        })->values()->all()];
    }

    public function queue(User $actor): array
    {
        $appointments=Appointment::visibleToOperationalUser($actor)
            ->whereDate('fecha_cita',now('America/Lima')->toDateString())
            ->where(function($q){
                $q->whereHas('operationalReview',fn($r)=>$r->whereNotNull('ready_at')->orWhere('requested_count','>',0))
                    ->orWhereHas('events',fn($e)=>$e->whereIn('event_type',[ManualReevaluationService::MARKED,ManualReevaluationService::CLEARED,self::MEDICAL_INDICATED]));
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
            $status=$summary['ready']?'READY':($summary['pre_pending']?'PRE_PENDING':($summary['resumed']?'RESUMED':($summary['declined']?'DECLINED':$review['status'])));
            if($summary['pending'])$status='PENDING';
            if(!$summary['ready'] && $status==='READY')$status='NOT_READY';
            if(!$summary['ready'] && !$summary['pre_pending'] && !$summary['pending'] && !$summary['resumed'] && !$summary['declined'] && !$procedures['requested_count'] && $direct['version']) {
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
