<?php
namespace Tests\Support;

use App\Models\Appointment;
use App\Services\Scheduling\OperationalProcedureService as Procedures;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

abstract class AgendaV2TestCase extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    protected $admin, $admission, $commercial, $a, $catalog;
    protected function setUp(): void
    {
        parent::setUp();
        config(['scheduling.enabled'=>true,'app.debug'=>false]);
        $this->admin=$this->agendaReader('ADMINISTRADOR');
        $this->admission=$this->agendaReader('ADMISION');
        $this->commercial=$this->agendaReader('COMERCIAL');
        $this->catalog=$this->createAppointmentCatalog();
        $this->a=$this->lifecycleAppointment($this->admin,$this->catalog,['fecha_cita'=>now('America/Lima')->toDateString()]);
        $this->actingAs($this->admin);
    }
    protected function change(string $action, array $extra=[], ?int $version=null, ?string $key=null, ?int $id=null)
    {
        $id=$id??$this->a->id;
        $version=$version??(int)(DB::table('appointment_operational_reviews')->where('appointment_id',$id)->value('version')??0);
        return $this->postJson(route('scheduling.mvp.admission.procedures',[$id,$action]),
            ['request_key'=>$key??(string)Str::uuid(),'expected_version'=>$version]+$extra);
    }
    protected function select(array $keys=['radiography','laboratory']) { return $this->change('selection',['procedures'=>$keys]); }
    protected function complete(string $key='radiography',bool $done=true) { return $this->change('completion',['procedure'=>$key,'completed'=>$done]); }
    protected function detail() {return $this->getJson(route('scheduling.mvp.admission.detail',$this->a->id));}
    protected function queue() {return $this->getJson(route('scheduling.mvp.admission.reevaluations'));}

}
