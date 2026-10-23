<?php
namespace Tests\Feature\Scheduling;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;
class OperationalDateTest extends TestCase
{
    use RefreshDatabase,BuildsAgendaLifecycleData;
    public function test_agenda_schedules_and_admission_share_lima_day_at_utc_midnight():void
    {
        config(['scheduling.enabled'=>true,'scheduling.operational_timezone'=>'America/Lima']);
        $actor=$this->agendaReader('ADMISION');$this->actingAs($actor);
        Carbon::setTestNow(Carbon::parse('2026-10-15 00:35:00','UTC'));
        try{
            foreach(['/scheduling-mvp/agenda','/admissionist/doctor-schedule','/scheduling-mvp/admission'] as $url)
                $this->get($url)->assertOk()->assertViewHas('today','2026-10-14');
        }finally{Carbon::setTestNow();}
    }
    public function test_legacy_lists_and_calendar_share_lima_day_at_a_utc_month_boundary():void
    {
        config(['scheduling.enabled'=>true,'scheduling.operational_timezone'=>'America/Lima']);
        Carbon::setTestNow(Carbon::parse('2026-11-01 00:35:00','UTC'));
        try{
            $owner=$this->agendaReader('ADMISION');$catalog=$this->createAppointmentCatalog();
            $shown=$this->lifecycleAppointment($owner,$catalog,['fecha_cita'=>'2026-10-31']);
            $this->lifecycleAppointment($owner,$catalog,['fecha_cita'=>'2026-11-01']);
            foreach(['ADMISION'=>'/admissionist/appointment','ADMINISTRADOR'=>'/admin/appointment','RECEPCION'=>'/receptionist/appointment'] as $role=>$url){
                $this->actingAs($this->agendaReader($role))->get($url)->assertRedirect(route('scheduling.mvp.agenda'));
                $this->get(route('scheduling.mvp.agenda'))->assertOk()->assertViewHas('today','2026-10-31');
                $this->postJson(route('scheduling.mvp.admission.feed'),['fecha'=>'2026-10-31','site_id'=>null])->assertOk()->assertJsonPath('total',1)->assertJsonPath('rows.0.id',$shown->id);
            }
        }finally{Carbon::setTestNow();}
    }
}
