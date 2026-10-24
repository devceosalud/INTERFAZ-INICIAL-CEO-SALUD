<?php
namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Site;
use App\Models\User;
use App\Services\Billing\AppointmentEconomicPosition;
use App\Support\Billing\Money;
use App\Support\Scheduling\AdmissionStatus;
use App\Support\Scheduling\{AdmissionAccess,ClinicalAccess};
use App\Support\Scheduling\AgendaQuery;
use App\Support\Scheduling\SchedulingCapability as C;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Administrative projection of existing appointments. No new payment or clinical store. */
class AdmissionBoardService
{
    public const VISUAL_FILTERS = [
        'LISTO_REEVALUACION'=>'Naranja · Listo para reevaluación', 'ATENDIDO'=>'Turquesa · Atendido', 'PRESENTE'=>'Turquesa · Paciente presente',
        'PAGO_REAL'=>'Amarillo · Pago real', 'CONFIRMADA_SIN_ADELANTO'=>'Amarillo · Confirmada sin adelanto', 'DESACTIVADA'=>'Rojo · Cancelada / retiro',
        'REEMBOLSO_SOLICITADO'=>'Rojo · Reembolso solicitado', 'SALIDA_REGISTRADA'=>'Salida registrada',
        'CONCILIAR_PAGO'=>'Revisión financiera', 'EXONERADA'=>'Exonerada autorizada', 'RESERVA_SIN_PAGO'=>'Reserva sin pago', 'SIN_PAGO_REAL'=>'Sin pago real',
    ];
    private const COLUMNS = ['id','numero_cita','user_id','responsible_user_id','patient_id','doctor_id','service_id','site_id',
        'tipo_agendamiento','fecha_cita','hora_cita','estado_cita','estado_agenda','estado_pagado','precio_programado','total_pagado',
        'attendance_mode','catalog_price','discount_type','discount_amount','discount_actor_user_id','discount_reason','motivo_exoneracion','economic_source','es_exonerado','autorizado_por','pricing_rule','is_postoperative','has_diabetic_wound',
        'hora_llegada','hora_llamado','hora_atencion','hora_atendido','hora_salida'];

    public function __construct(private OperationalAgendaAppointmentService $appointments,
        private AppointmentEconomicPosition $economics) {}

    public function defaultSite(): ?Site
    {
        $matches = Site::activo()->get(['id','nombre'])->filter(fn ($s) =>
            mb_strtolower(trim($s->nombre)) === 'magdalena del mar');
        return $matches->count() === 1 ? $matches->first() : null;
    }

    public function board(array $filters, User $actor): array
    {
        $clinicalOnly=AdmissionAccess::clinicalOnly($actor);
        $doctors = Doctor::query()->when($clinicalOnly,fn($q)=>$q->whereKey($actor->fresh()->doctor_id))->when(!empty($filters['doctor_id']), fn ($q) => $q->whereKey($filters['doctor_id']))
            ->when(!empty($filters['specialty_id']), fn ($q) => $q->where('specialty_id',$filters['specialty_id']))->pluck('id')->all();
        $query = new AgendaQuery($doctors, Carbon::parse($filters['fecha']),
            Carbon::parse($filters['hasta'] ?? $filters['fecha']), $filters['site_id'] ?? null);
        $rows = $this->appointments->rangeQuery($query, $actor->id)->select(self::COLUMNS)
            ->withExists(['events as confirmed_without_deposit'=>fn($q)=>$q->where('event_type','CONFIRMACION_SIN_ADELANTO')])
            ->when($clinicalOnly,fn($q)=>$q->whereIn('estado_agenda',['LEGADO','CONFIRMADA']))
            ->when(!empty($filters['estado']), fn ($q) => $q->where('estado_cita',$filters['estado']))
            ->when(!empty($filters['patient']), function ($q) use ($filters) {
                // Bound parameters and escaped LIKE metacharacters; POST body keeps identity out of URLs.
                $term = '%'.str_replace(['!','%','_'],['!!','!%','!_'],trim($filters['patient'])).'%';
                $q->whereHas('patient', function ($p) use ($term) {
                    $p->where(function ($names) use ($term) {
                        foreach (['nombre','apellido_paterno','apellido_materno','numero_identidad','historia_clinica'] as $column)
                            $names->orWhereRaw($column." LIKE ? ESCAPE '!'",[$term]);
                    });
                });
            })
            ->with(['operationalReview','activeProcedureRequests'])->orderBy('fecha_cita')->orderBy('hora_cita')->orderBy('id')->limit(2001)->get();
        if ($rows->count() > 2000) throw ValidationException::withMessages(['fecha'=>'Reduce el rango o filtra un médico: el panel admite hasta 2000 citas por consulta.']);
        // Quick categories select the list, not the population represented by the KPIs.
        $scopeRows=$rows;
        app(ManualReevaluationService::class)->load($scopeRows);
        $rows=$this->filterRows($scopeRows,$filters);
        $history=app(RescheduleHistory::class);
        $historyFilter=fn($a)=>$this->filterRows($clinicalOnly?$a->filter(fn($item)=>(int)$item->doctor_id===(int)$actor->doctor_id && in_array($item->estado_agenda,['LEGADO','CONFIRMADA'],true)):$a,$filters);
        $timelineHistory=$history->forRange($query,$actor,array_diff_key($filters,['history_before'=>true]),$historyFilter,2000,true,35);
        if($timelineHistory['has_more'])throw ValidationException::withMessages(['fecha'=>'Reduce el rango para mostrar todas las referencias históricas en orden cronológico.']);
        $timeline=$rows->map(fn($a)=>['type'=>'appointment','id'=>$a->id,'date'=>substr($a->fecha_cita,0,10),'time'=>substr($a->hora_cita,0,5)])
            ->concat(collect($timelineHistory['items'])->map(fn($h)=>['type'=>'reschedule','date'=>$h['from_date'],'time'=>$h['from_time']]+$h))
            ->sortBy(fn($r)=>$r['date'].'|'.$r['time'].'|'.($r['type']==='reschedule'?'0':'1').'|'.str_pad((string)($r['event_id']??$r['id']),20,'0',STR_PAD_LEFT))->values();
        $page = min(max(1,(int)($filters['page'] ?? 1)),max(1,(int)ceil($timeline->count()/30)));
        $focusIndex = empty($filters['focus_appointment_id']) ? false : $timeline->search(fn($r)=>
            $r['type']==='appointment' && (int)$r['id']===(int)$filters['focus_appointment_id']);
        if ($focusIndex !== false) $page = intdiv($focusIndex,30)+1;
        $pageTimeline=$timeline->slice(($page-1)*30,30)->values();
        $pageRows=$rows->whereIn('id',$pageTimeline->where('type','appointment')->pluck('id'))->values();
        $positions = $this->economics->forAppointments($scopeRows,$pageRows->pluck('id')->all());
        $kpis = array_fill_keys(array_keys(AdmissionStatus::LABELS),0) + ['reservas'=>0,'otros'=>0,'reevaluaciones'=>0,'reevaluaciones_pendientes'=>0,'reevaluaciones_prependientes'=>0];
        $totals = ['precio'=>0,'pagos_reales'=>0,'pagado_historico'=>0,'credito'=>0,'saldo'=>0,'requieren_revision'=>0];
        foreach ($scopeRows as $a) {
            $kpis['total']++; $kpis[AdmissionStatus::group($a)]++;
            if(AppointmentReevaluationService::ready($a)) $kpis['reevaluaciones']++;
            if(AppointmentReevaluationService::pending($a)||AppointmentReevaluationService::prePending($a)) $kpis['reevaluaciones_pendientes']++;
            if(AppointmentReevaluationService::prePending($a)) $kpis['reevaluaciones_prependientes']++;
            $p = $positions[$a->id]; $totals['precio'] += $p['price_cents'];
            if ($p['authority'] === 'UNALLOCATED') { $totals['requieren_revision']++; continue; }
            $totals[$p['authority'] === 'LEGACY_SNAPSHOT' ? 'pagado_historico' : 'pagos_reales'] += $p['paid_cents'];
            $totals['credito'] += $p['credit_cents']; $totals['saldo'] += $p['balance_cents'];
        }
        foreach (['precio','pagos_reales','pagado_historico','credito','saldo'] as $key) $totals[$key] = Money::decimal($totals[$key]);
        // Only the visible page needs identity and label relations; totals use minimal rows.
        $pageRows->load(['operationalReview','patient:id,nombre,apellido_paterno,apellido_materno,historia_clinica,numero_identidad',
            'doctor:id,nombre,specialty_id','doctor.specialty:id,nombre','service:id,nombre',
            'responsibleUser:id,name','user:id,name','site:id,nombre']);
        $reasons=Appointment::whereIn('id',$pageRows->pluck('id'))->pluck('motivo_consulta','id');
        $origins=$history->latestForAppointments($pageRows,$actor);
        $kpis['reevaluaciones_confirmadas_pendientes']=$kpis['reevaluaciones_pendientes']-$kpis['reevaluaciones_prependientes'];
        return $this->protect(['kpis'=>$kpis,'totals'=>$totals,'total'=>$rows->count(),'page'=>$page,'per_page'=>30,'can_update_care'=>$actor->can(C::UPDATE),
            'scope_total'=>$scopeRows->count(),'timeline_total'=>$timeline->count(),'timeline'=>$pageTimeline->all(),
            'focus_found'=>$focusIndex!==false,
            'reschedules'=>$history->forRange($query,$actor,$filters,$historyFilter,30),
            'rows'=>$pageRows->map(fn ($a) => $this->row($a,$positions[$a->id],$actor)+['reschedule_origin'=>$origins[$a->id]??null,
                'motivo_resumen'=>mb_strimwidth(\App\Support\Scheduling\OperationalText::trim((string)($reasons[$a->id]??'')),0,160,'…')])->values()->all()],$clinicalOnly);
    }

    private function filterRows(Collection $rows,array $filters): Collection
    {
        app(ManualReevaluationService::class)->load($rows);
        if(!empty($filters['only_pending_reevaluations']))$rows=$rows->filter(fn($a)=>AppointmentReevaluationService::pending($a)||AppointmentReevaluationService::prePending($a))->values();
        if(($filters['reevaluation_stage']??'')==='PRE_PENDING')$rows=$rows->filter(fn($a)=>AppointmentReevaluationService::prePending($a))->values();
        if(($filters['reevaluation_stage']??'')==='PENDING')$rows=$rows->filter(fn($a)=>AppointmentReevaluationService::pending($a))->values();
        if(!empty($filters['grupo']))$rows=$rows->filter(fn($a)=>AdmissionStatus::group($a)===$filters['grupo'])->values();
        if(!empty($filters['only_reevaluations'])||($filters['tab']??'citas')==='reevaluaciones')$rows=$rows->filter(fn($a)=>AppointmentReevaluationService::ready($a))->values();
        if(!empty($filters['visual'])){
            $positions=$this->economics->forAppointments($rows,[]);
            $rows=$rows->filter(function($a)use($filters,$positions){
                $p=\App\Support\Scheduling\AgendaAppointmentPresentation::of($a,$positions[$a->id]);
                return $filters['visual']==='CONCILIAR_PAGO'?in_array('CONCILIAR_PAGO',array_column($p['badges'],'clave'),true):$p['clave']===$filters['visual'];
            })->values();
        }
        return $rows;
    }

    private function row(Appointment $a, array $p, User $actor): array
    {
        $manual=app(ManualReevaluationService::class);
        return ['manual_reevaluation'=>$manual->state($a)+['can_update'=>$manual->canUpdate($actor)&&$manual->eligible($a,$actor)],
            'patient_alert'=>app(PatientOperationalAlertService::class)->summary((int)$a->patient_id,$actor,(int)$a->id),
            'reevaluation'=>AppointmentReevaluationService::summary($a), 'id'=>$a->id,'numero'=>$a->numero_cita,'fecha'=>substr($a->fecha_cita,0,10),'hora'=>substr($a->hora_cita,0,5),
            'patient'=>trim(implode(' ',array_filter([$a->patient?->apellido_paterno,$a->patient?->apellido_materno,$a->patient?->nombre]))),
            'documento'=>$a->patient?->numero_identidad,'historia'=>$a->patient?->historia_clinica,
            'doctor'=>$a->doctor?->nombre,'especialidad'=>$a->doctor?->specialty?->nombre,'servicio'=>$a->service?->nombre,
            'responsable'=>$a->responsibleUser?->name,'sede'=>$a->site?->nombre,
            'attendance_mode'=>$a->attendance_mode ?? 'PRESENCIAL','estado_cita'=>$a->estado_cita,'estado_agenda'=>$a->estado_agenda,'estado_pagado'=>$a->estado_pagado,
            'payment_methods'=>array_values(array_unique(array_column($p['payment_details'],'method'))),
            'procedure_progress'=>['requested'=>(int)($a->operationalReview?->requested_count??0),'completed'=>(int)($a->operationalReview?->completed_count??0),
                'status'=>OperationalProcedureService::status($a),'origin'=>$a->operationalReview?->origin],
            'presentation'=>\App\Support\Scheduling\AgendaAppointmentPresentation::of($a,$p),'grupo'=>AdmissionStatus::group($a),'pricing_rule'=>$a->pricing_rule,'postoperatorio'=>(bool)$a->is_postoperative,
            'tiempos'=>collect(['llegada'=>$a->hora_llegada,'llamado'=>$a->hora_llamado,'inicio'=>$a->hora_atencion,'fin'=>$a->hora_atendido,'salida'=>$a->hora_salida])->map(fn ($time) => $time ? Carbon::parse($time,config('app.timezone'))->toISOString() : null)->all(),
            'economy'=>['precio'=>Money::decimal($p['price_cents']),'pagado'=>Money::decimal($p['paid_cents']),
                'credito'=>Money::decimal($p['credit_cents']),'saldo'=>Money::decimal($p['balance_cents']),'fuente'=>$p['authority'],'financial_status'=>$p['financial_status']],
            'agenda_url'=>route('scheduling.mvp.agenda',['fecha'=>substr($a->fecha_cita,0,10),'doctor_id'=>$a->doctor_id,'appointment_id'=>$a->id])];
    }

    public function detail(int $id, User $actor): array
    {
        $clinicalOnly=AdmissionAccess::clinicalOnly($actor);
        if($clinicalOnly)ClinicalAccess::appointment(ClinicalAccess::actor($actor),Appointment::findOrFail($id));
        $a = Appointment::visibleToOperationalUser($actor)->select(array_merge(self::COLUMNS,['motivo_consulta']))->with([
            'patient:id,nombre,apellido_paterno,apellido_materno,historia_clinica,numero_identidad,telefono,telefono_secundario,channel_id,interaction_medium_id',
            'patient.channel:id,nombre','patient.interactionMedium:id,nombre',
            'doctor:id,nombre,specialty_id','doctor.specialty:id,nombre','service:id,nombre','site:id,nombre','user:id,name','responsibleUser:id,name'])->whereKey($id)->firstOr(fn () => abort(404));
        app(ManualReevaluationService::class)->load(collect([$a]));
        $a->load('activeProcedureRequests');
        $previous = Appointment::visibleToOperationalUser($actor)->when($actor->hasRole('MEDICO'),fn($q)=>$q->where('doctor_id',$actor->doctor_id)->whereIn('estado_agenda',['LEGADO','CONFIRMADA']))->select(self::COLUMNS)->where('patient_id',$a->patient_id)->where('id','<>',$a->id)
            ->with(['doctor:id,nombre,specialty_id','doctor.specialty:id,nombre','service:id,nombre','site:id,nombre','patient:id,nombre,apellido_paterno,apellido_materno,historia_clinica,numero_identidad',
                'responsibleUser:id,name','user:id,name'])->orderByDesc('fecha_cita')->orderByDesc('hora_cita')->limit(20)->get();
        $positions = $this->economics->forAppointments($previous->push($a));
        $history = $previous->reject(fn ($item) => $item->id === $a->id)->map(function ($item) use ($positions,$actor) {
            // Administrative history deliberately omits reason, observations and clinical content.
            return ['fecha'=>substr($item->fecha_cita,0,10),'hora'=>substr($item->hora_cita,0,5),
                'doctor'=>$item->doctor?->nombre,'servicio'=>$item->service?->nombre,'estado_cita'=>$item->estado_cita,
                'estado_agenda'=>$item->estado_agenda,'economy'=>$this->row($item,$positions[$item->id],$actor)['economy']];
        })->values();
        $canSeeCreator = \App\Support\Scheduling\AgendaCreatorVisibility::allows($actor);
        $events = $a->events()->with('actor:id,name')->orderByDesc('occurred_at')->orderByDesc('id')->limit(30)->get()
            ->map(fn ($e) => ['type'=>$e->event_type,'at'=>$e->occurred_at->toISOString(),'actor'=>\App\Support\Scheduling\AgendaCreatorVisibility::eventActor($a,$e,$canSeeCreator)]);
        // Only payment attachments are linked here. Medical evidence stays behind its own access checks.
        $documents = $a->documents()->whereNotNull('payment_id')->get(['id','appointment_id','payment_id','reviewed_at'])
            ->map(fn ($d) => ['id'=>$d->id,'payment_id'=>$d->payment_id,'reviewed_at'=>$d->reviewed_at?->toISOString(),
                'download_url'=>route('scheduling.mvp.documents.download',[$a->id,$d->id])]);
        // Appointment-bound operational files only; no clinical narrative or medical-order evidence.
        $operationalDocuments = $actor->can(\App\Support\Scheduling\AppointmentDocumentAccess::VIEW)
            ? $a->documents()->whereNull('payment_id')->whereIn('document_kind',\App\Support\Scheduling\AppointmentDocumentAccess::KINDS)
                ->where(fn($q)=>$q->whereNull('purpose')->orWhereNotIn('purpose',['REEVALUATION_ORDER',\App\Support\Scheduling\AppointmentDocumentAccess::PATIENT_PURPOSE]))
                ->whereNotIn('id',\App\Models\AppointmentReevaluationIndication::select('evidence_document_id')->whereNotNull('evidence_document_id'))
                ->get(['id','appointment_id','document_kind','label','url','private_path'])->map(fn($d)=>[
                    'id'=>$d->id,'kind'=>$d->document_kind,'label'=>$d->label,
                    'url'=>$d->url?:route('scheduling.mvp.documents.download',[$a->id,$d->id])])
            : collect();
        $followUp = app(AdmissionTaskService::class)->listForAppointment($a, $actor);
        $procedures = app(OperationalProcedureService::class)->detail($a, $actor);
        $medicalFollowup=app(MedicalProcedureFollowupService::class)->referralContext($actor,$a,$procedures);
        $payload=$this->protect([
            'exoneration_confirmation'=>AdmissionExonerationConfirmation::eligible($a,$actor) && Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->exists()
                ? ['url'=>route('scheduling.mvp.admission.confirm-exoneration',$id),'revision'=>AppointmentManagement::revision($a->fresh())] : null,
            'without_deposit_confirmation'=>ReservationPaymentService::withoutDepositEligible($a,$actor,$positions[$a->id])
                && Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->exists()
                ? ['url'=>route('scheduling.mvp.admission.confirm-without-deposit',$id),'revision'=>AppointmentManagement::revision($a->fresh())] : null,
            'reevaluation'=>app(AppointmentReevaluationService::class)->detail($a,$actor,$procedures),'procedures'=>$procedures,'medical_followup'=>$medicalFollowup,'care_reversal'=>app(AdmissionCareService::class)->reversalOffer($a,$actor),
            'appointment'=>$this->row($a,$positions[$a->id],$actor)+['motivo_consulta'=>$a->motivo_consulta],'contact'=>[
            'telefono'=>$a->patient?->telefono,'telefono_secundario'=>$a->patient?->telefono_secundario,
            'canal'=>$a->patient?->channel?->nombre,'medio'=>$a->patient?->interactionMedium?->nombre],
            'events'=>$events,'history'=>$history,'documents'=>$documents,'operational_documents'=>$operationalDocuments,
            'patient_documents_url'=>route('scheduling.mvp.documents.index',$a->id),
            'can_view_operational_documents'=>$actor->can(\App\Support\Scheduling\AppointmentDocumentAccess::VIEW),'payments'=>$positions[$a->id]['payment_details'],
            'operational_tasks'=>$followUp['tasks'],'can_manage_operational_tasks'=>$followUp['can_manage'],
            'can_complete_operational_tasks'=>$followUp['can_complete'],
            'care_actions'=>$this->careActions($a,$actor),
            'can_register_arrival'=>AdmissionCareService::canRegisterArrival($a,$actor),
            'can_decline_operational_reevaluation'=>AppointmentReevaluationService::operatorCanDecline($actor,$a),
            'can_confirm_operational_reevaluation'=>AppointmentReevaluationService::operatorCanConfirm($actor)
                && AppointmentReevaluationService::awaitingConfirmation($a),
            'medical_reevaluation'=>AppointmentReevaluationService::summary($a),
            'care_fallback'=>UnlinkedDoctorAdmissionFallback::eligible($a,$actor) && UnlinkedDoctorAdmissionFallback::allowedState($a)!==null,

            'patient_url'=>route('patients.operational.index',['patient_id'=>$a->patient_id,'appointment_id'=>$a->id,'from_agenda'=>1,
                'doctor_id'=>$a->doctor_id,'agenda_date'=>substr($a->fecha_cita,0,10)])],$clinicalOnly);
        $policy=\App\Support\Scheduling\AdmissionRolePolicy::class;
        $historyAllowed=false;
        if($policy::history($actor)) {try{$policy::documentsAppointment($actor,$id);$historyAllowed=true;}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){if(!in_array($e->getStatusCode(),[403,404]))throw $e;}}
        $payload['detail_tabs']=['history'=>$historyAllowed,'problem'=>$policy::operational($actor)];
        $payload['patient_alert_url']=($policy::operational($actor)||$policy::administrator($actor)||$actor->hasRole('MEDICO')&&(int)$actor->doctor_id===(int)$a->doctor_id)?route('scheduling.mvp.admission.patient-alert',$id):null;
        $payload['patient_documents_url']=$historyAllowed?($actor->hasRole('MEDICO')?route('scheduling.mvp.admission.documents.index',$id):route('scheduling.mvp.documents.index',$id)):null;
        // Admisión-specific document policy; legacy Agenda document capabilities are unchanged.
        if(!$historyAllowed||$actor->hasRole('MEDICO')){$payload['documents']=[];$payload['operational_documents']=[];$payload['can_view_operational_documents']=false;}
        if(!$historyAllowed){$payload['history']=[];$payload['events']=[];}
        elseif($actor->hasRole('MEDICO')){
            $payload['history']=$history->map(function($item){unset($item['economy']);return $item;})->values()->all();
            $payload['events']=$events->filter(fn($e)=>preg_match('/^(ATENCION|PACIENTE|LLEGADA|SALIDA|REEVALUACION)/',$e['type']))->values()->all();
        }
        return $payload;
    }

    private function protect(array $payload,bool $clinicalOnly): array
    {
        $payload['can_view_economy']=!$clinicalOnly;
        if(!$clinicalOnly)return $payload;
        $redact=function(array $row):array{
            foreach(['economy','estado_pagado','payment_methods','pricing_rule','documento','responsable','agenda_url'] as $key)$row[$key]=null;
            $row['presentation']['badges']=array_values(array_filter($row['presentation']['badges']??[],
                fn($b)=>in_array($b['clave'],['CIRUGIA','RESONANCIA','PIE_DIABETICO','HERIDA','POSTOPERATORIO','ADICIONAL','FUERA_HORARIO','PENDIENTE_REEVALUACION','LISTO_REEVALUACION','PROCEDIMIENTOS','PRESENTE','SALIDA_REGISTRADA','TELECONSULTA'],true)));
            return $row;
        };
        if(isset($payload['rows'])){
            $payload['rows']=array_map($redact,$payload['rows']);
            $payload['totals']=null;$payload['can_update_care']=false;
            // The historical reference remains on the same screen and never opens generic Agenda.
            foreach($payload['reschedules']['items'] as &$item)$item['agenda_url']=null;
            unset($item);
        }
        if(isset($payload['appointment'])){
            $payload['appointment']=$redact($payload['appointment']);
            foreach(['payments','documents','operational_documents','history','operational_tasks'] as $key)$payload[$key]=[];
            $payload['contact']=array_fill_keys(['telefono','telefono_secundario','canal','medio'],null);
            foreach(['patient_url','patient_documents_url','care_restriction','care_reversal','commercial_arrival_url','commercial_departure_url','exoneration_confirmation','without_deposit_confirmation'] as $key)$payload[$key]=null;
            foreach(['can_view_operational_documents','can_manage_operational_tasks','can_complete_operational_tasks','care_fallback'] as $key)$payload[$key]=false;
            $payload['care_actions']=$payload['care_actions']??[];
        }
        return $payload;
    }

    public function careActions(Appointment $a, User $actor): array
    {
        if($a->estado_cita==='ATENDIDO')return AdmissionCareService::canRegisterDeparture($a,$actor)
            ? ['SALIDA_REGISTRADA'] : [];
        if (($a->attendance_mode ?? 'PRESENCIAL')==='TELECONSULTA') return [];
        $ownPhysician=AppointmentReevaluationService::physicianCanAct($actor,$a);
        if (!$actor->can(C::UPDATE) && !$ownPhysician) return AdmissionCareService::canRegisterArrival($a,$actor)
            ? ['EN_ESPERA'] : [];
        if (!in_array($a->estado_agenda,['LEGADO','CONFIRMADA'],true)) return [];
        $linked=AppointmentReevaluationService::linkedPhysicianExists($a);
        if(!$ownPhysician && !$linked && in_array($a->estado_cita,['EN_ESPERA','LLAMANDO','EN_ATENCION'],true)) {
            $fallback=UnlinkedDoctorAdmissionFallback::eligible($a,$actor)?UnlinkedDoctorAdmissionFallback::allowedState($a):null;
            return $a->estado_cita==='EN_ESPERA'?array_values(array_filter(['LLAMANDO',$fallback])):($fallback?[$fallback]:[]);
        }
        if($a->estado_cita==='EN_ATENCION'){
            if($linked && !$ownPhysician) return [];
            if(AppointmentReevaluationService::resumed($a)) return $ownPhysician ? ['ATENDIDO'] : [];
            if(AppointmentReevaluationService::indicated($a)){
                if(!$ownPhysician) return [];
                if(AppointmentReevaluationService::ready($a)) return AppointmentReevaluationService::confirmed($a) ? ['INICIAR_REEVALUACION'] : [];
                return ['RETIRAR_REEVALUACION'];
            }
            if($ownPhysician) return ['INDICAR_REEVALUACION','ATENDIDO'];
            return ['ATENDIDO'];
        }
        if($ownPhysician && in_array($a->estado_cita,['EN_ESPERA','LLAMANDO'],true))return $a->hora_llegada?['EN_ATENCION']:[];
        return match ($a->estado_cita) {
            'PROGRAMADO','CONFIRMADO','PACIENTE_LLEGO'=>['EN_ESPERA'],
            'EN_ESPERA'=>$linked&&!$ownPhysician?['LLAMANDO']:['LLAMANDO','EN_ATENCION'],
            'LLAMANDO'=>$linked&&!$ownPhysician?[]:['EN_ATENCION'],
            default=>[],
        };
    }
}
