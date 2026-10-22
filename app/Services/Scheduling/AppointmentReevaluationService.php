<?php
namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Read-only union of independent return-to-doctor decisions; never migrates or invents evidence. */
final class AppointmentReevaluationService
{
    // Read contract for the future verified physician writer. No public writer is enabled yet.
    public const INDICATED = 'REEVALUACION_MEDICA_INDICADA';
    public const WITHDRAWN = 'REEVALUACION_MEDICA_RETIRADA';

    public static function loadIndications(\Illuminate\Support\Collection $appointments): void
    {
        if ($appointments->isEmpty()) return;
        $events=\App\Models\AppointmentEvent::whereIn('id',function($q)use($appointments){
            $q->from('appointment_events')->selectRaw('MAX(id)')->whereIn('appointment_id',$appointments->pluck('id'))
                ->whereIn('event_type',[self::INDICATED,self::WITHDRAWN])->groupBy('appointment_id');
        })->with('actor:id,name')->get()->keyBy('appointment_id');
        foreach($appointments as $a)$a->setRelation('medicalReevaluationEvent',$events->get($a->id));
    }

    public static function indication(Appointment $a): array
    {
        $e=$a->relationLoaded('medicalReevaluationEvent')?$a->getRelation('medicalReevaluationEvent'):null;
        $matching=$e && (int)($e->metadata['version']??0)===1
            && ($e->metadata['source']??null)==='MEDICAL_RETURN_SAME_APPOINTMENT_V1'
            && (int)($e->metadata['doctor_id']??0)===(int)$a->doctor_id;
        return ['indicated'=>(bool)($matching && $e->event_type===self::INDICATED),
            'event_id'=>$matching?(int)$e->id:null,'at'=>$matching?$e->occurred_at->toISOString():null,
            'doctor_id'=>$matching?(int)$a->doctor_id:null,
            'actor'=>$matching?['id'=>(int)$e->actor_user_id,'name'=>$e->actor?->name??'Usuario no disponible']:null];
    }

    public static function pending(Appointment $a): bool
    {
        return self::indication($a)['indicated'] && !self::ready($a)
            && $a->estado_cita==='EN_ATENCION' && !$a->hora_atendido && !$a->hora_salida
            && in_array($a->estado_agenda,['LEGADO','CONFIRMADA'],true);
    }

    public static function directReady(Appointment $a): bool
    {
        return $a->relationLoaded('manualReevaluationEvent')
            && $a->getRelation('manualReevaluationEvent')?->event_type === ManualReevaluationService::MARKED
            && $a->estado_cita === 'ATENDIDO' && !$a->hora_salida
            && in_array($a->estado_agenda, ['LEGADO','CONFIRMADA'], true);
    }

    public static function ready(Appointment $a): bool
    {
        return self::directReady($a) || OperationalProcedureService::ready($a);
    }

    public static function summary(Appointment $a): array
    {
        $origins=[];
        if (self::directReady($a)) $origins[]='MANUAL_DIRECT';
        if (OperationalProcedureService::ready($a)) {
            $origins[]=$a->operationalReview->origin === 'MANUAL' ? 'MANUAL_PARTIAL' : 'AUTOMATIC';
        }
        return ['ready'=>count($origins)>0,'origins'=>$origins,'pending'=>self::pending($a)];
    }

    public function detail(Appointment $a, User $actor, array $procedures): array
    {
        $a->loadMissing('operationalReview.decidedBy');
        if (!$a->relationLoaded('manualReevaluationEvent')) app(ManualReevaluationService::class)->load(collect([$a]));
        $manual=app(ManualReevaluationService::class);
        $direct=$manual->state($a)+['active'=>self::directReady($a),
            'can_update'=>$manual->canUpdate($actor)&&$manual->eligible($a)];
        $history=$this->history($a->id,$actor);
        return self::summary($a)+['medical_indication'=>self::indication($a),'direct'=>$direct,'procedural'=>$procedures['review'],
            'history'=>$history['rows'],'history_meta'=>array_diff_key($history,['rows'=>true])];
    }

    /** Stable append-only snapshot: later actions cannot shift or duplicate the next audit page. */
    public function history(int $id, User $actor, int $page=1, ?array $cursor=null): array
    {
        Appointment::visibleToOperationalUser($actor)->whereKey($id)->firstOr(fn()=>abort(404));
        $cursor??=['direct_until'=>(int)(DB::table('appointment_events')->where('appointment_id',$id)
            ->whereIn('event_type',[ManualReevaluationService::MARKED,ManualReevaluationService::CLEARED,self::INDICATED,self::WITHDRAWN])->max('id')??0),
            'procedure_until'=>(int)(DB::table('appointment_procedure_events')->where('appointment_id',$id)->max('id')??0)];
        $direct=DB::table('appointment_events as e')->leftJoin('users as u','u.id','=','e.actor_user_id')
            ->where('e.appointment_id',$id)->where('e.id','<=',$cursor['direct_until'])
            ->whereIn('e.event_type',[ManualReevaluationService::MARKED,ManualReevaluationService::CLEARED,self::INDICATED,self::WITHDRAWN])
            ->select(['e.id','e.event_type as action','e.occurred_at as at','e.actor_user_id','u.name',DB::raw("CASE WHEN e.event_type IN ('REEVALUACION_MEDICA_INDICADA','REEVALUACION_MEDICA_RETIRADA') THEN 'MEDICAL_INDICATION' ELSE 'MANUAL_DIRECT' END as source"),DB::raw('NULL as snapshot')]);
        $structured=DB::table('appointment_procedure_events as e')->leftJoin('users as u','u.id','=','e.actor_user_id')
            ->where('e.appointment_id',$id)->where('e.id','<=',$cursor['procedure_until'])
            ->select(['e.id','e.action','e.occurred_at as at','e.actor_user_id','u.name',DB::raw("'PROCEDURES' as source"),'e.after as snapshot']);
        $rows=DB::query()->fromSub($direct->unionAll($structured),'audit')->orderByDesc('at')->orderByDesc('source')->orderByDesc('id')
            ->offset(($page-1)*40)->limit(41)->get();
        return ['page'=>$page,'has_more'=>$rows->count()>40,'cursor'=>$cursor,'rows'=>$rows->take(40)->map(function($e){
            $entry=['id'=>($e->source!=='PROCEDURES'?'event-':'procedure-').$e->id,'source'=>$e->source,'action'=>$e->action,
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
