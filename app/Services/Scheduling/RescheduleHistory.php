<?php
namespace App\Services\Scheduling;

use App\Models\{Appointment,AppointmentEvent,User};
use App\Support\Scheduling\{AgendaQuery,AgendaCreatorVisibility};
use Illuminate\Support\Collection;

/** Read-only annotations. Never fed into occupancy, capacity or FullCalendar events. */
final class RescheduleHistory
{
    public function forRange(AgendaQuery $range,User $actor,array $filters=[],?callable $filterAppointments=null,int $limit=200,bool $includePatient=false,int $maxBatches=10): array
    {
        $empty=['has_more'=>false,'next_cursor'=>null,'items'=>[]];
        if(!$range->doctorIds() || !$actor->fresh()?->is_active)return $empty;
        $query=AppointmentEvent::where('event_type','REPROGRAMADA')
            ->where('metadata->from->fecha_cita','>=',$range->start()->toDateString())
            ->where('metadata->from->fecha_cita','<',$range->end()->addDay()->toDateString())
            ->where(fn($q)=>$q->whereIn('metadata->from->doctor_id',$range->doctorIds())->orWhere(fn($legacy)=>$legacy
                ->whereNull('metadata->from->doctor_id')->whereHas('appointment',fn($a)=>$a->whereIn('doctor_id',$range->doctorIds()))))
            ->when($range->siteId()!==null,fn($q)=>$q->where(fn($s)=>$s->where('metadata->from->site_id',$range->siteId())->orWhereNull('metadata->from->site_id')))
            ->whereHas('appointment',function($q)use($actor,$filters){
                $q->visibleToOperationalUser($actor)->when(!empty($filters['estado']),fn($a)=>$a->where('estado_cita',$filters['estado']));
                if(!empty($filters['patient'])){
                    $term='%'.str_replace(['!','%','_'],['!!','!%','!_'],trim($filters['patient'])).'%';
                    $q->whereHas('patient',fn($p)=>$p->where(function($names)use($term){
                        foreach(['nombre','apellido_paterno','apellido_materno','numero_identidad','historia_clinica'] as $c)$names->orWhereRaw($c." LIKE ? ESCAPE '!'",[$term]);
                    }));
                }
            });
        $accepted=collect();$cursor=$filters['history_before']??null;$batches=0;
        // Apply computed admission filters before pagination; older matching references are never skipped.
        do{
            $batch=(clone $query)->when($cursor,fn($q)=>$q->where('id','<',$cursor))->with(['appointment.operationalReview','appointment.patient:id,nombre,apellido_paterno,apellido_materno','actor:id,name'])->orderByDesc('id')->limit(201)->get();
            $batches++;
            if($batch->isEmpty())break;
            $appointments=$batch->pluck('appointment')->filter()->unique('id');
            $allowed=$filterAppointments?$filterAppointments($appointments)->pluck('id')->all():$appointments->pluck('id')->all();
            foreach($batch as $event){
                if(in_array($event->appointment_id,$allowed) && $this->valid($event))$accepted->push($event);
                if($accepted->count()>$limit)break;
            }
            $cursor=$batch->last()->id;
        }while($accepted->count()<=$limit && $batch->count()===201 && $batches<$maxBatches);
        $visible=$accepted->take($limit);
        // Bounded work even when computed filters reject many references. Continue via the cursor.
        $scanLimited=$accepted->count()<=$limit && $batch->count()===201 && $batches===$maxBatches;
        return ['has_more'=>$accepted->count()>$limit||$scanLimited,'next_cursor'=>$accepted->count()>$limit?(int)$visible->last()->id:($scanLimited?(int)$cursor:null),
            'items'=>$visible->map(fn($event)=>$this->item($event,$actor,$includePatient))->values()->all()];
    }

    public function latestForAppointments(Collection $appointments,User $actor): array
    {
        if($appointments->isEmpty())return [];
        $ids=AppointmentEvent::selectRaw('MAX(id)')->where('event_type','REPROGRAMADA')->whereIn('appointment_id',$appointments->pluck('id'))->groupBy('appointment_id');
        return AppointmentEvent::whereIn('id',$ids)->whereHas('appointment',fn($q)=>$q->visibleToOperationalUser($actor))
            ->with(['appointment','actor:id,name'])->get()->filter(fn($e)=>$this->valid($e))->mapWithKeys(fn($e)=>[$e->appointment_id=>$this->item($e,$actor)])->all();
    }

    private function valid(AppointmentEvent $e): bool
    {
        return $e->appointment && isset($e->metadata['from']['fecha_cita'],$e->metadata['from']['hora_cita'],$e->metadata['to']['fecha_cita'],$e->metadata['to']['hora_cita']);
    }

    private function item(AppointmentEvent $event,User $actor,bool $includePatient=false): array
    {
        $from=$event->metadata['from'];$to=$event->metadata['to'];$a=$event->appointment;
        $time=substr($from['hora_cita'],0,5);$duration=(int)($from['duracion_cita']??0);
        $minutes=(int)substr($time,0,2)*60+(int)substr($time,3,2);$end=min(1440,$minutes+$duration);
        return ['event_id'=>(int)$event->id,'appointment_id'=>(int)$event->appointment_id,
            'doctor_id'=>(int)($from['doctor_id']??$a->doctor_id),'site_id'=>isset($from['site_id'])?(int)$from['site_id']:null,
            'from_date'=>substr($from['fecha_cita'],0,10),'from_time'=>$time,'from_end'=>sprintf('%02d:%02d',intdiv($end,60),$end%60),
            'duration_minutes'=>$duration,'duration_slots'=>(int)($from['duration_slots']??1),
            'to_date'=>substr($to['fecha_cita'],0,10),'to_time'=>substr($to['hora_cita'],0,5),
            'current_date'=>substr($a->fecha_cita,0,10),'current_time'=>substr($a->hora_cita,0,5),'current_doctor_id'=>(int)$a->doctor_id,
            'agenda_url'=>route('scheduling.mvp.agenda',['fecha'=>substr($a->fecha_cita,0,10),'doctor_id'=>$a->doctor_id,'appointment_id'=>$a->id]),
            'admission_url'=>route('scheduling.mvp.admission',['fecha'=>substr($a->fecha_cita,0,10),'doctor_id'=>$a->doctor_id,'site_id'=>$a->site_id??'','appointment_id'=>$a->id]),
            'actor'=>AgendaCreatorVisibility::allows($actor)?($event->actor?->name??'Usuario histórico'):null,
            'occurred_at'=>$event->occurred_at?->toISOString(),'historical'=>true,'occupies_slot'=>false]
            +($includePatient?['patient'=>trim(implode(' ',array_filter([$a->patient?->apellido_paterno,$a->patient?->apellido_materno,$a->patient?->nombre])))]:[]);
    }
}
