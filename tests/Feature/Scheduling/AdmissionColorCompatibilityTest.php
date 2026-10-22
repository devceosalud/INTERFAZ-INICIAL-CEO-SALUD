<?php
namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAdmissionColorData;
use Tests\TestCase;

class AdmissionColorCompatibilityTest extends TestCase
{
    use RefreshDatabase,BuildsAdmissionColorData;
    protected function tearDown():void{\Carbon\Carbon::setTestNow();parent::tearDown();}
    public function test_thirteen_server_states_keep_the_historical_palette_for_all_four_authorized_profiles():void
    {
        $f=$this->admissionColorFixture();$before=Appointment::orderBy('id')->get()->toArray();$events=DB::table('appointment_events')->count();
        foreach(['admin','commercial','actor','medical'] as $role){
            $payload=$this->actingAs($f[$role])->postJson(route('scheduling.mvp.admission.feed'),['fecha'=>'2026-10-21','site_id'=>null])->assertOk();
            $rows=collect($payload->json('rows'))->keyBy('id');$this->assertCount(13,$rows);
            foreach($f['cases'] as $case){$row=$rows[$case['id']];$this->assertSame($case['state'],$row['estado_cita']);
                $this->assertSame($case['key'],$row['presentation']['clave'],$role.' '.$case['name']);
                $this->assertSame($case['background'],$row['presentation']['fringe']['color'],$role.' '.$case['name']);
                $this->assertSame(in_array($case['name'],['REEVALUACION_PENDIENTE','PROCEDIMIENTOS_INCOMPLETOS']),$row['reevaluation']['pending']);
                $this->assertSame($case['name']==='REEVALUACION_LISTA',$row['reevaluation']['ready']);
            }
        }
        $this->assertSame($before,Appointment::orderBy('id')->get()->toArray());$this->assertSame($events,DB::table('appointment_events')->count());
        $this->assertDatabaseCount('payments',1);$this->assertDatabaseCount('appointments',13);
    }
}
