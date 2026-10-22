<?php
namespace App\Services\Scheduling;

use App\Models\{Appointment,User};
use App\Support\Scheduling\{AgendaAppointmentPresentation,ClinicalAccess,ClinicalCapability as C};

final class MedicalBoardService
{
    public function feed(User $actor,array $filters): array
    {
        $u=ClinicalAccess::actor($actor);
        $query=Appointment::where('doctor_id',$u->doctor_id)->whereIn('estado_agenda',['LEGADO','CONFIRMADA'])
            ->whereDate('fecha_cita',$filters['fecha'])
            ->when(!empty($filters['site_id']),fn($q)=>$q->where('site_id',$filters['site_id']))
            ->when(!empty($filters['patient']),function($q)use($filters){
                $term='%'.str_replace(['!','%','_'],['!!','!%','!_'],trim($filters['patient'])).'%';
                $q->whereHas('patient',fn($p)=>$p->where(function($names)use($term){
                    foreach(['nombre','apellido_paterno','apellido_materno','historia_clinica'] as $col)$names->orWhereRaw($col." LIKE ? ESCAPE '!'",[$term]);
                }));
            })->with(['patient','service:id,nombre','site:id,nombre','operationalReview'])->orderBy('hora_cita')->orderBy('id');
        $page=$query->paginate(30,['*'],'page',$filters['page']??1);
        app(ManualReevaluationService::class)->load($page->getCollection());
        return ['rows'=>$page->getCollection()->map(fn($a)=>$this->row($a))->values()->all(),
            'total'=>$page->total(),'page'=>$page->currentPage(),'last_page'=>$page->lastPage()];
    }

    private function row(Appointment $a): array
    {
        $p=AgendaAppointmentPresentation::of($a,[]);
        // Use the established operational palette, without implying payment or exposing amounts.
        return ['id'=>$a->id,'fecha'=>substr($a->fecha_cita,0,10),'hora'=>substr($a->hora_cita,0,5),
            'patient'=>trim($a->patient?->apellido_paterno.' '.$a->patient?->apellido_materno.' '.$a->patient?->nombre),
            'historia'=>$a->patient?->historia_clinica,'service'=>$a->service?->nombre,'site'=>$a->site?->nombre,
            'estado_cita'=>$a->estado_cita,'reevaluation'=>AppointmentReevaluationService::summary($a),
            'presentation'=>['color'=>$p['color'],'fondo'=>$p['fringe']['color'],'badges'=>array_values(array_filter($p['badges'],
                fn($b)=>in_array($b['clave'],['PENDIENTE_REEVALUACION','LISTO_REEVALUACION','PROCEDIMIENTOS','SALIDA_REGISTRADA'],true)))]];
    }

    public function detail(int $id,User $actor): array
    {
        $u=ClinicalAccess::actor($actor);
        $a=Appointment::with(['patient','service:id,nombre','site:id,nombre','operationalReview'])->findOrFail($id);
        ClinicalAccess::appointment($u,$a);
        app(ManualReevaluationService::class)->load(collect([$a]));
        $open=($a->attendance_mode??'PRESENCIAL')==='PRESENCIAL' && !$a->hora_atendido && !$a->hora_salida;
        $indication=AppointmentReevaluationService::indication($a);
        $actions=[];
        if($open && $a->hora_llegada && in_array($a->estado_cita,['EN_ESPERA','LLAMANDO'],true) && !$a->hora_atencion && ClinicalAccess::has($u,C::START))$actions[]='start';
        if($open && $a->estado_cita==='EN_ATENCION'){
            if(!AppointmentReevaluationService::pending($a) && ClinicalAccess::has($u,C::FINISH))$actions[]='finish';
            if(!$indication['indicated'] && !AppointmentReevaluationService::ready($a) && ClinicalAccess::has($u,C::INDICATE))$actions[]='indicate';
            if($indication['indicated'] && ClinicalAccess::has($u,C::WITHDRAW))$actions[]='withdraw';
        }
        return ['appointment'=>$this->row($a)+['motivo_consulta'=>$a->motivo_consulta,
                'tiempos'=>$a->only(['hora_llegada','hora_atencion','hora_atendido','hora_salida'])],
            'revision'=>MedicalCareService::revision($a),'actions'=>$actions,'indication'=>$indication,
            'procedures'=>app(OperationalProcedureService::class)->detail($a,$u,true),
            'events'=>$a->events()->whereIn('event_type',['ATENCION_MEDICA_INICIADA','ATENCION_MEDICA_FINALIZADA',
                AppointmentReevaluationService::INDICATED,AppointmentReevaluationService::WITHDRAWN])
                ->with('actor:id,name')->orderByDesc('id')->limit(40)->get()->map(fn($e)=>[
                    'id'=>$e->id,'type'=>$e->event_type,'at'=>$e->occurred_at->toISOString(),'actor'=>$e->actor?->name,'reason'=>$e->motivo])->all()];
    }
}
