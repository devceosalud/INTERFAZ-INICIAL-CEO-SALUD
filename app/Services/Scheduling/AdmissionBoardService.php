<?php
namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Site;
use App\Models\User;
use App\Services\Billing\AppointmentEconomicPosition;
use App\Support\Billing\Money;
use App\Support\Scheduling\AdmissionStatus;
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
        'PAGO_REAL'=>'Amarillo · Pago real', 'DESACTIVADA'=>'Rojo · Cancelada / retiro',
        'REEMBOLSO_SOLICITADO'=>'Rojo · Reembolso solicitado', 'SALIDA_REGISTRADA'=>'Salida registrada',
        'CONCILIAR_PAGO'=>'Revisión financiera', 'RESERVA_SIN_PAGO'=>'Reserva sin pago', 'SIN_PAGO_REAL'=>'Sin pago real',
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
        $doctors = Doctor::query()->when(!empty($filters['doctor_id']), fn ($q) => $q->whereKey($filters['doctor_id']))
            ->when(!empty($filters['specialty_id']), fn ($q) => $q->where('specialty_id',$filters['specialty_id']))->pluck('id')->all();
        $query = new AgendaQuery($doctors, Carbon::parse($filters['fecha']),
            Carbon::parse($filters['hasta'] ?? $filters['fecha']), $filters['site_id'] ?? null);
        $rows = $this->appointments->rangeQuery($query, $actor->id)->select(self::COLUMNS)
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
            ->with('operationalReview')->orderBy('fecha_cita')->orderBy('hora_cita')->orderBy('id')->limit(2001)->get();
        if ($rows->count() > 2000) throw ValidationException::withMessages(['fecha'=>'Reduce el rango o filtra un médico: el panel admite hasta 2000 citas por consulta.']);
        $rows=$this->filterRows($rows,$filters);
        $page = min(max(1,(int)($filters['page'] ?? 1)),max(1,(int)ceil($rows->count()/30)));
        $positions = $this->economics->forAppointments($rows,$rows->slice(($page-1)*30,30)->pluck('id')->all());
        $kpis = array_fill_keys(array_keys(AdmissionStatus::LABELS),0) + ['reservas'=>0,'otros'=>0,'reevaluaciones'=>0];
        $totals = ['precio'=>0,'pagos_reales'=>0,'pagado_historico'=>0,'credito'=>0,'saldo'=>0,'requieren_revision'=>0];
        foreach ($rows as $a) {
            $kpis['total']++; $kpis[AdmissionStatus::group($a)]++;
            if(AppointmentReevaluationService::ready($a)) $kpis['reevaluaciones']++;
            $p = $positions[$a->id]; $totals['precio'] += $p['price_cents'];
            if ($p['authority'] === 'UNALLOCATED') { $totals['requieren_revision']++; continue; }
            $totals[$p['authority'] === 'LEGACY_SNAPSHOT' ? 'pagado_historico' : 'pagos_reales'] += $p['paid_cents'];
            $totals['credito'] += $p['credit_cents']; $totals['saldo'] += $p['balance_cents'];
        }
        foreach (['precio','pagos_reales','pagado_historico','credito','saldo'] as $key) $totals[$key] = Money::decimal($totals[$key]);
        // Only the visible page needs identity and label relations; totals use minimal rows.
        $pageRows=$rows->slice(($page-1)*30,30);
        $pageRows->load(['operationalReview','patient:id,nombre,apellido_paterno,apellido_materno,historia_clinica,numero_identidad',
            'doctor:id,nombre,specialty_id','doctor.specialty:id,nombre','service:id,nombre',
            'responsibleUser:id,name','user:id,name','site:id,nombre']);
        $reasons=Appointment::whereIn('id',$pageRows->pluck('id'))->pluck('motivo_consulta','id');
        $history=app(RescheduleHistory::class);$origins=$history->latestForAppointments($pageRows,$actor);
        return ['kpis'=>$kpis,'totals'=>$totals,'total'=>$rows->count(),'page'=>$page,'per_page'=>30,'can_update_care'=>$actor->can(C::UPDATE),
            'reschedules'=>$history->forRange($query,$actor,$filters,fn($a)=>$this->filterRows($a,$filters),30),
            'rows'=>$pageRows->map(fn ($a) => $this->row($a,$positions[$a->id],$actor)+['reschedule_origin'=>$origins[$a->id]??null,
                'motivo_resumen'=>mb_strimwidth(\App\Support\Scheduling\OperationalText::trim((string)($reasons[$a->id]??'')),0,160,'…')])->values()->all()];
    }

    private function filterRows(Collection $rows,array $filters): Collection
    {
        app(ManualReevaluationService::class)->load($rows);
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
        return ['manual_reevaluation'=>$manual->state($a)+['can_update'=>$manual->canUpdate($actor)&&$manual->eligible($a)],
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
        $a = Appointment::visibleToOperationalUser($actor)->select(array_merge(self::COLUMNS,['motivo_consulta']))->with([
            'patient:id,nombre,apellido_paterno,apellido_materno,historia_clinica,numero_identidad,telefono,telefono_secundario,channel_id,interaction_medium_id',
            'patient.channel:id,nombre','patient.interactionMedium:id,nombre',
            'doctor:id,nombre,specialty_id','doctor.specialty:id,nombre','service:id,nombre','site:id,nombre','user:id,name','responsibleUser:id,name'])->whereKey($id)->firstOr(fn () => abort(404));
        app(ManualReevaluationService::class)->load(collect([$a]));
        $previous = Appointment::visibleToOperationalUser($actor)->select(self::COLUMNS)->where('patient_id',$a->patient_id)->where('id','<>',$a->id)
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
        return ['reevaluation'=>app(AppointmentReevaluationService::class)->detail($a,$actor,$procedures),'procedures'=>$procedures,'care_reversal'=>app(AdmissionCareService::class)->reversalOffer($a,$actor),
            'appointment'=>$this->row($a,$positions[$a->id],$actor)+['motivo_consulta'=>$a->motivo_consulta],'contact'=>[
            'telefono'=>$a->patient?->telefono,'telefono_secundario'=>$a->patient?->telefono_secundario,
            'canal'=>$a->patient?->channel?->nombre,'medio'=>$a->patient?->interactionMedium?->nombre],
            'events'=>$events,'history'=>$history,'documents'=>$documents,'operational_documents'=>$operationalDocuments,
            'patient_documents_url'=>route('scheduling.mvp.documents.index',$a->id),
            'can_view_operational_documents'=>$actor->can(\App\Support\Scheduling\AppointmentDocumentAccess::VIEW),'payments'=>$positions[$a->id]['payment_details'],
            'operational_tasks'=>$followUp['tasks'],'can_manage_operational_tasks'=>$followUp['can_manage'],
            'can_complete_operational_tasks'=>$followUp['can_complete'],
            'care_actions'=>$this->careActions($a,$actor),
            'patient_url'=>route('patients.operational.index',['patient_id'=>$a->patient_id,'appointment_id'=>$a->id,'from_agenda'=>1,
                'doctor_id'=>$a->doctor_id,'agenda_date'=>substr($a->fecha_cita,0,10)])];
    }

    public function careActions(Appointment $a, User $actor): array
    {
        if (($a->attendance_mode ?? 'PRESENCIAL')==='TELECONSULTA') return [];
        if (!$actor->can(C::UPDATE) || !in_array($a->estado_agenda,['LEGADO','CONFIRMADA'],true)) return [];
        return match ($a->estado_cita) {
            'PROGRAMADO','CONFIRMADO','PACIENTE_LLEGO'=>['EN_ESPERA'],
            'EN_ESPERA'=>['LLAMANDO','EN_ATENCION'], 'LLAMANDO'=>['EN_ATENCION'], 'EN_ATENCION'=>['ATENDIDO'], 'ATENDIDO'=>$a->hora_salida ? [] : ['SALIDA_REGISTRADA'], default=>[],
        };
    }
}
