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
    private const COLUMNS = ['id','numero_cita','user_id','responsible_user_id','patient_id','doctor_id','service_id','site_id',
        'fecha_cita','hora_cita','estado_cita','estado_agenda','estado_pagado','precio_programado','total_pagado',
        'economic_source','es_exonerado','autorizado_por','pricing_rule','is_postoperative','has_diabetic_wound',
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
            ->orderBy('fecha_cita')->orderBy('hora_cita')->orderBy('id')->limit(2001)->get();
        if ($rows->count() > 2000) throw ValidationException::withMessages(['fecha'=>'Reduce el rango o filtra un médico: el panel admite hasta 2000 citas por consulta.']);
        if (!empty($filters['grupo'])) $rows = $rows->filter(fn ($a) => AdmissionStatus::group($a) === $filters['grupo'])->values();
        $page = min(max(1,(int)($filters['page'] ?? 1)),max(1,(int)ceil($rows->count()/30)));
        $positions = $this->economics->forAppointments($rows,$rows->slice(($page-1)*30,30)->pluck('id')->all());
        $kpis = array_fill_keys(array_keys(AdmissionStatus::LABELS),0) + ['reservas'=>0,'otros'=>0];
        $totals = ['precio'=>0,'pagos_reales'=>0,'pagado_historico'=>0,'credito'=>0,'saldo'=>0,'requieren_revision'=>0];
        foreach ($rows as $a) {
            $kpis['total']++; $kpis[AdmissionStatus::group($a)]++;
            $p = $positions[$a->id]; $totals['precio'] += $p['price_cents'];
            if ($p['authority'] === 'UNALLOCATED') { $totals['requieren_revision']++; continue; }
            $totals[$p['authority'] === 'LEGACY_SNAPSHOT' ? 'pagado_historico' : 'pagos_reales'] += $p['paid_cents'];
            $totals['credito'] += $p['credit_cents']; $totals['saldo'] += $p['balance_cents'];
        }
        foreach (['precio','pagos_reales','pagado_historico','credito','saldo'] as $key) $totals[$key] = Money::decimal($totals[$key]);
        // Only the visible page needs identity and label relations; totals use minimal rows.
        $pageRows=$rows->slice(($page-1)*30,30);
        $pageRows->load(['patient:id,nombre,apellido_paterno,apellido_materno,historia_clinica,numero_identidad',
            'doctor:id,nombre,specialty_id','doctor.specialty:id,nombre','service:id,nombre',
            'responsibleUser:id,name','user:id,name','site:id,nombre']);
        return ['kpis'=>$kpis,'totals'=>$totals,'total'=>$rows->count(),'page'=>$page,'per_page'=>30,'can_update_care'=>$actor->can(C::UPDATE),
            'rows'=>$pageRows->map(fn ($a) => $this->row($a,$positions[$a->id]))->values()->all()];
    }

    private function row(Appointment $a, array $p): array
    {
        return ['id'=>$a->id,'numero'=>$a->numero_cita,'fecha'=>substr($a->fecha_cita,0,10),'hora'=>substr($a->hora_cita,0,5),
            'patient'=>trim(implode(' ',array_filter([$a->patient?->apellido_paterno,$a->patient?->apellido_materno,$a->patient?->nombre]))),
            'documento'=>$a->patient?->numero_identidad,'historia'=>$a->patient?->historia_clinica,
            'doctor'=>$a->doctor?->nombre,'especialidad'=>$a->doctor?->specialty?->nombre,'servicio'=>$a->service?->nombre,
            'responsable'=>$a->responsibleUser?->name,'sede'=>$a->site?->nombre,
            'estado_cita'=>$a->estado_cita,'estado_agenda'=>$a->estado_agenda,'estado_pagado'=>$a->estado_pagado,
            'payment_methods'=>array_values(array_unique(array_column($p['payment_details'],'method'))),
            'presentation'=>\App\Support\Scheduling\AgendaAppointmentPresentation::of($a,$p),'grupo'=>AdmissionStatus::group($a),'pricing_rule'=>$a->pricing_rule,'postoperatorio'=>(bool)$a->is_postoperative,
            'tiempos'=>collect(['llegada'=>$a->hora_llegada,'llamado'=>$a->hora_llamado,'inicio'=>$a->hora_atencion,'fin'=>$a->hora_atendido,'salida'=>$a->hora_salida])->map(fn ($time) => $time ? Carbon::parse($time,config('app.timezone'))->toISOString() : null)->all(),
            'economy'=>['precio'=>Money::decimal($p['price_cents']),'pagado'=>Money::decimal($p['paid_cents']),
                'credito'=>Money::decimal($p['credit_cents']),'saldo'=>Money::decimal($p['balance_cents']),'fuente'=>$p['authority']],
            'agenda_url'=>route('scheduling.mvp.agenda',['fecha'=>substr($a->fecha_cita,0,10),'doctor_id'=>$a->doctor_id,'appointment_id'=>$a->id])];
    }

    public function detail(int $id, User $actor): array
    {
        $a = Appointment::visibleToAgendaUser($actor->id)->select(self::COLUMNS)->with([
            'patient:id,nombre,apellido_paterno,apellido_materno,historia_clinica,numero_identidad,telefono,telefono_secundario,channel_id,interaction_medium_id',
            'patient.channel:id,nombre','patient.interactionMedium:id,nombre',
            'doctor:id,nombre,specialty_id','doctor.specialty:id,nombre','service:id,nombre','site:id,nombre','user:id,name','responsibleUser:id,name'])->whereKey($id)->firstOr(fn () => abort(404));
        $previous = Appointment::visibleToAgendaUser($actor->id)->select(self::COLUMNS)->where('patient_id',$a->patient_id)->where('id','<>',$a->id)
            ->with(['doctor:id,nombre,specialty_id','doctor.specialty:id,nombre','service:id,nombre','site:id,nombre','patient:id,nombre,apellido_paterno,apellido_materno,historia_clinica,numero_identidad',
                'responsibleUser:id,name','user:id,name'])->orderByDesc('fecha_cita')->orderByDesc('hora_cita')->limit(20)->get();
        $positions = $this->economics->forAppointments($previous->push($a));
        $history = $previous->reject(fn ($item) => $item->id === $a->id)->map(function ($item) use ($positions) {
            // Administrative history deliberately omits reason, observations and clinical content.
            return ['fecha'=>substr($item->fecha_cita,0,10),'hora'=>substr($item->hora_cita,0,5),
                'doctor'=>$item->doctor?->nombre,'servicio'=>$item->service?->nombre,'estado_cita'=>$item->estado_cita,
                'estado_agenda'=>$item->estado_agenda,'economy'=>$this->row($item,$positions[$item->id])['economy']];
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
                ->where(fn($q)=>$q->whereNull('purpose')->orWhere('purpose','<>','REEVALUATION_ORDER'))
                ->whereNotIn('id',\App\Models\AppointmentReevaluationIndication::select('evidence_document_id')->whereNotNull('evidence_document_id'))
                ->get(['id','appointment_id','document_kind','label','url','private_path'])->map(fn($d)=>[
                    'id'=>$d->id,'kind'=>$d->document_kind,'label'=>$d->label,
                    'url'=>$d->url?:route('scheduling.mvp.documents.download',[$a->id,$d->id])])
            : collect();
        return ['appointment'=>$this->row($a,$positions[$a->id]),'contact'=>[
            'telefono'=>$a->patient?->telefono,'telefono_secundario'=>$a->patient?->telefono_secundario,
            'canal'=>$a->patient?->channel?->nombre,'medio'=>$a->patient?->interactionMedium?->nombre],
            'events'=>$events,'history'=>$history,'documents'=>$documents,'operational_documents'=>$operationalDocuments,
            'can_view_operational_documents'=>$actor->can(\App\Support\Scheduling\AppointmentDocumentAccess::VIEW),'payments'=>$positions[$a->id]['payment_details'],
            'care_actions'=>$this->careActions($a,$actor),
            'patient_url'=>route('patients.operational.index',['patient_id'=>$a->patient_id,'appointment_id'=>$a->id,'from_agenda'=>1,
                'doctor_id'=>$a->doctor_id,'agenda_date'=>substr($a->fecha_cita,0,10)])];
    }

    public function careActions(Appointment $a, User $actor): array
    {
        if (!$actor->can(C::UPDATE) || !in_array($a->estado_agenda,['LEGADO','CONFIRMADA'],true)) return [];
        return match ($a->estado_cita) {
            'PROGRAMADO','CONFIRMADO','PACIENTE_LLEGO'=>['EN_ESPERA'],
            'EN_ESPERA'=>['LLAMANDO','EN_ATENCION'], 'LLAMANDO'=>['EN_ATENCION'], 'EN_ATENCION'=>['ATENDIDO'], 'ATENDIDO'=>$a->hora_salida ? [] : ['SALIDA_REGISTRADA'], default=>[],
        };
    }
}
