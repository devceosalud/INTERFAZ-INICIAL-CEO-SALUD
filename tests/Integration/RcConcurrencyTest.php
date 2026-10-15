<?php
namespace Tests\Integration;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\Payment;
use App\Support\Scheduling\SchedulingCapability as C;
use App\Services\Scheduling\DoctorScheduleMutation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Process\Process;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;
class RcConcurrencyTest extends TestCase
{
    use BuildsAgendaLifecycleData;
    private $actor,$peer,$catalog,$patient;
    public function createApplication(){return \Tests\Support\IsolatedAgendaQa::boot();}
    protected function setUp():void{
        parent::setUp();$this->artisan('migrate:fresh',['--force'=>true])->assertExitCode(0);
        config(['scheduling.enabled'=>true,'scheduling.pilot_payment_without_manual_cash_shift'=>true]);
        $this->actor=$this->agendaReader('ADMISION');$this->peer=$this->agendaReader('COMERCIAL');
        foreach([$this->actor,$this->peer] as $u)foreach([C::CREATE,C::UPDATE,C::SUBMIT_PAYMENT,C::CREATE_ADDITIONAL,'doctor.schedule.manage'] as $cap)$u->givePermissionTo(Permission::findOrCreate($cap,'web'));
        $this->catalog=$this->createAppointmentCatalog();$this->catalog['rate']->update(['nombre'=>'TARIFA ESTANDAR','tipo_tarifa'=>'MONTO_FIJO']);$this->patient=$this->createPatient($this->actor);$this->actingAs($this->actor);
        $this->beforeApplicationDestroyed(function(){$this->artisan('migrate:fresh',['--force'=>true])->assertExitCode(0);\Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated=false;});
    }
    private function race(array $requests):array{
        $dir=storage_path('app/qa-rc/races/'.Str::uuid());mkdir($dir,0777,true);$jobs=[];
        foreach($requests as $i=>$r){$out=$dir.'/'.$i.'.json';$in=$dir.'/'.$i.'.input.json';file_put_contents($in,json_encode($r+['release'=>$dir.'/release']));$p=new Process([PHP_BINARY,base_path('tests/Support/agenda-qa-request.php'),$in,$out],base_path(),null,null,40);$p->start();$jobs[]=[$p,$out];}
        try{$end=microtime(true)+20;do{$ready=true;foreach($jobs as [$p,$out])$ready=$ready&&file_exists($out.'.ready');if($ready)break;usleep(10000);}while(microtime(true)<$end);$this->assertTrue($ready,'RC workers failed to reach barrier');touch($dir.'/release');$results=[];
            foreach($jobs as [$p,$out]){$p->wait();$this->assertTrue($p->isSuccessful(),$p->getErrorOutput());$results[]=json_decode(file_get_contents($out),true,512,JSON_THROW_ON_ERROR);}return $results;
        }finally{foreach($jobs as [$p])if($p->isRunning())$p->stop();}
    }
    private function statuses(array $r):array{$s=array_column($r,'status');sort($s);return $s;}
    public function test_two_schedule_edits_cannot_overwrite_the_same_version():void{
        $s=$this->catalog['schedule'];$data=['doctor_schedule_id_edit'=>$s->id,'expected_version'=>DoctorScheduleMutation::version($s),'doctor_id_edit'=>$s->doctor_id,'fecha_cita_edit'=>$s->fecha_cita,'hora_inicio_edit'=>'08:00','hora_fin_edit'=>'14:00','duracion_edit_cita'=>30];
        $r=['actor'=>$this->actor->id,'method'=>'PUT','path'=>'/admissionist/doctor-schedule/update','payload'=>$data];$other=array_replace($r,['actor'=>$this->peer->id,'payload'=>array_replace($data,['hora_fin_edit'=>'15:00'])]);
        $this->assertSame([200,409],$this->statuses($this->race([$r,$other])));$this->assertSame(1,(int)$s->fresh()->lock_version);$this->assertDatabaseCount('doctor_schedule_events',1);$this->assertDatabaseCount('appointments',0);
    }
    public function test_two_overlapping_schedule_creates_are_serialized_under_the_doctor_lock():void{
        $payload=['doctor_id'=>$this->catalog['doctor']->id,'scope'=>'single','fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_inicio'=>'14:00','hora_fin'=>'16:00','duracion_cita'=>30];
        $r=['actor'=>$this->actor->id,'path'=>'/admissionist/doctor-schedule/store','payload'=>$payload];
        $other=array_replace($r,['actor'=>$this->peer->id,'payload'=>array_replace($payload,['hora_inicio'=>'14:30','hora_fin'=>'16:30'])]);
        $results=$this->race([$r,$other]);$this->assertSame([200,422],$this->statuses($results));
        $winner=collect($results)->firstWhere('status',200);$loser=collect($results)->firstWhere('status',422);$this->assertSame(1,$winner['body']['code']);$this->assertArrayHasKey('hora_inicio',$loser['body']['errors']);
        $this->assertDatabaseCount('doctor_schedules',2);$this->assertDatabaseCount('doctor_schedule_events',1);$this->assertDatabaseCount('appointments',0);
    }
    public function test_two_care_changes_from_same_state_produce_one_event():void{
        $a=$this->lifecycleAppointment($this->actor,$this->catalog,['patient_id'=>$this->patient->id,'estado_agenda'=>'CONFIRMADA','estado_cita'=>'PROGRAMADO'],$this->patient);
        $r=['actor'=>$this->actor->id,'path'=>'/scheduling-mvp/admission/appointments/'.$a->id.'/care','payload'=>['state'=>'EN_ESPERA','expected_state'=>'PROGRAMADO','request_key'=>(string)Str::uuid()]];
        $other=array_replace($r,['actor'=>$this->peer->id,'payload'=>array_replace($r['payload'],['request_key'=>(string)Str::uuid()])]);
        $this->assertSame([200,409],$this->statuses($this->race([$r,$other])));$this->assertSame('EN_ESPERA',$a->fresh()->estado_cita);
        // One delegated legacy mutation plus one admission action audit, never duplicated by the loser.
        $this->assertDatabaseCount('appointment_events',2);
        foreach(['EDICION_HEREDADA','ADMISION_ESTADO_ACTUALIZADO'] as $event)$this->assertSame(1,DB::table('appointment_events')->where('event_type',$event)->where('appointment_id',$a->id)->count());
        $this->assertDatabaseCount('appointment_operations',1);$this->assertDatabaseCount('payments',0);
    }
    public function test_concurrent_additional_retry_converts_once_without_new_money_or_appointment():void{
        $payload=['request_key'=>(string)Str::uuid(),'mode'=>'RESERVE','booking_type'=>'REGULAR','doctor_id'=>$this->catalog['doctor']->id,'patient_id'=>$this->patient->id,'service_id'=>$this->catalog['service']->id,'fecha_cita'=>$this->catalog['schedule']->fecha_cita,'hora_cita'=>'09:00','duracion_cita'=>30];
        $id=$this->postJson('/scheduling-mvp/agenda/registrations',$payload)->assertCreated()->json('appointment.appointment_id');
        $other=$this->createPatient($this->actor,['numero_identidad'=>'70000991','historia_clinica'=>'RC-RACE-OTHER']);
        $this->postJson('/scheduling-mvp/agenda/registrations',array_replace($payload,['request_key'=>(string)Str::uuid(),'patient_id'=>$other->id,'payment'=>['amount'=>'20','method'=>'EFECTIVO']]))->assertCreated();
        $url='/scheduling-mvp/agenda/appointments/'.$id.'/additional-conversion';$offer=$this->getJson($url)->assertOk()->assertJsonPath('available',true)->json();
        $r=['actor'=>$this->actor->id,'path'=>$url,'payload'=>['request_key'=>(string)Str::uuid(),'expected_version'=>$offer['expected_version'],'confirmed'=>true]];
        $this->assertSame([200,200],$this->statuses($this->race([$r,$r])));$this->assertSame('ADICIONAL',Appointment::find($id)->tipo_agendamiento);$this->assertSame('PENDIENTE_CONFIRMACION',Appointment::find($id)->estado_agenda);$this->assertDatabaseCount('appointments',2);$this->assertDatabaseCount('payments',1);$this->assertEquals(20,Payment::sum('monto'));$this->assertSame(1,DB::table('appointment_events')->where('event_type','RESERVA_CONVERTIDA_ADICIONAL')->count());
    }
}
