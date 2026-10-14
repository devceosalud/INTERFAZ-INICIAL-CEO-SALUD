<?php
namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Models\Cashier;
use App\Models\CashierShift;
use App\Models\Payment;
use App\Models\Site;
use App\Models\VoucherSerie;
use App\Support\Scheduling\AdmissionStatus;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class AdmissionBoardTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    private $actor; private $catalog;
    protected function setUp(): void
    {
        parent::setUp();config(['scheduling.enabled'=>true,'app.debug'=>false]);
        $this->actor=$this->agendaReader('RECEPCION');$this->catalog=$this->createAppointmentCatalog();
        $this->actingAs($this->actor);
    }
    private function feed(array $extra=[]){return $this->postJson(route('scheduling.mvp.admission.feed'),array_replace(['fecha'=>now()->toDateString()],$extra));}
    private function appointment(array $extra=[]){return $this->lifecycleAppointment($this->actor,$this->catalog,$extra);}
    private function detail(int $id){return $this->getJson(route('scheduling.mvp.admission.detail',$id));}
    private function updatePermission():void {$this->actor->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));}
    private function care($a,string $state,?string $key=null,array $extra=[]){return $this->postJson(route('scheduling.mvp.admission.care',$a->id),array_replace(['state'=>$state,'expected_state'=>$a->estado_cita,'request_key'=>$key??(string)Str::uuid()],$extra));}

    public function test_default_is_actual_magdalena_id_and_today_in_lima_without_creating_site():void
    {
        $this->get(route('scheduling.mvp.admission'))->assertOk()->assertSee('Magdalena del Mar aún no')->assertSee(now('America/Lima')->toDateString());
        $this->assertDatabaseCount('sites',0);
        $site=$this->createSite(['codigo'=>'PREEXISTENTE','nombre'=>'Magdalena del Mar']);
        $this->createSite(['codigo'=>'OTRA','nombre'=>'Otra sede']);
        $this->get(route('scheduling.mvp.admission'))->assertOk()->assertViewHas('defaultSite',fn($s)=>$s->id===$site->id);
        $this->assertDatabaseCount('sites',2);
    }
    public function test_all_approved_groups_preserve_states_and_expose_inactive_subtypes():void
    {
        foreach(AdmissionStatus::STATES as $state)$this->appointment(['estado_cita'=>$state]);
        $before=Appointment::all()->map->getAttributes()->all();
        $r=$this->feed()->assertOk()->assertJsonPath('kpis.total',11)->assertJsonPath('kpis.por_llegar',2)
            ->assertJsonPath('kpis.en_espera',3)->assertJsonPath('kpis.en_consultorio',1)->assertJsonPath('kpis.atendidos',1)
            ->assertJsonPath('kpis.desactivadas',3)->assertJsonPath('kpis.otros',1);
        foreach(['CANCELADO','NO_ASISTIO','RETIRO'] as $state)$this->feed(['estado'=>$state])->assertOk()->assertJsonPath('total',1)->assertJsonPath('rows.0.estado_cita',$state)->assertJsonPath('kpis.desactivadas',1);
        $this->assertSame($before,Appointment::all()->map->getAttributes()->all());
        $this->assertDatabaseCount('payments',0);
    }
    public function test_pending_private_reservations_and_inconsistent_arrival_never_look_confirmed():void
    {
        $this->appointment(['estado_agenda'=>'PENDIENTE_CONFIRMACION','estado_cita'=>'PROGRAMADO','estado_pagado'=>'PAGADO','total_pagado'=>100]);
        $arrived=$this->appointment();$arrived->forceFill(['hora_llegada'=>now()])->save();
        $this->feed()->assertOk()->assertJsonPath('kpis.reservas',1)->assertJsonPath('kpis.por_llegar',0)->assertJsonPath('kpis.otros',1)
            ->assertJsonPath('rows.0.estado_agenda','PENDIENTE_CONFIRMACION')->assertJsonPath('rows.0.economy.pagado','0.00');
    }
    public function test_other_workers_private_reservations_are_absent_from_totals_search_history_and_detail():void
    {
        $other=$this->agendaReader('COMERCIAL');$patient=$this->createPatient($other,['nombre'=>'SOLOPRIVADO']);
        $hidden=$this->lifecycleAppointment($other,$this->catalog,['estado_agenda'=>'PENDIENTE_CONFIRMACION'], $patient);
        $this->feed()->assertOk()->assertJsonPath('total',0)->assertJsonPath('totals.precio','0.00');
        $this->feed(['patient'=>'SOLOPRIVADO'])->assertOk()->assertJsonPath('total',0);
        $this->detail($hidden->id)->assertNotFound();$this->detail(999999)->assertNotFound();
        $visible=$this->lifecycleAppointment($other,$this->catalog,[], $patient);
        $this->detail($visible->id)->assertOk()->assertJsonCount(0,'history');
    }
    public function test_site_filter_includes_legacy_null_but_never_relabels_or_reassigns_it():void
    {
        $mag=$this->createSite(['nombre'=>'Magdalena del Mar']);$other=$this->createSite(['codigo'=>'OTRA']);
        $legacy=$this->appointment();$this->appointment(['site_id'=>$mag->id]);$this->appointment(['site_id'=>$other->id]);
        $r=$this->feed()->assertOk()->assertJsonPath('total',2);
        $this->assertNull(collect($r->json('rows'))->firstWhere('id',$legacy->id)['sede']);
        $this->feed(['site_id'=>$other->id])->assertOk()->assertJsonPath('total',2);
        $this->feed(['site_id'=>null])->assertOk()->assertJsonPath('total',3);$this->assertNull($legacy->fresh()->site_id);
    }
    public function test_filters_and_pagination_keep_kpis_and_totals_on_same_set_and_escape_wildcards():void
    {
        for($i=0;$i<31;$i++)$this->appointment();
        $this->feed()->assertOk()->assertJsonPath('total',31)->assertJsonPath('kpis.total',31)->assertJsonPath('totals.precio','3100.00')->assertJsonCount(30,'rows');
        $this->feed(['page'=>2])->assertOk()->assertJsonCount(1,'rows')->assertJsonPath('kpis.total',31);
        $this->feed(['patient'=>'%'])->assertOk()->assertJsonPath('total',0);
        $this->feed(['patient'=>'_'])->assertOk()->assertJsonPath('total',0);
        $this->feed(['patient'=>'Baseline','doctor_id'=>$this->catalog['doctor']->id,'specialty_id'=>$this->catalog['specialty']->id,'grupo'=>'por_llegar'])->assertOk()->assertJsonPath('total',31);
        $this->feed(['hasta'=>now()->addDays(31)->toDateString()])->assertUnprocessable();
        $this->feed(['estado'=>'PAGADO'])->assertUnprocessable();
    }
    public function test_admin_history_omits_clinical_text_private_paths_evidence_and_unrecorded_timestamps():void
    {
        $a=$this->appointment(['observaciones'=>'DIAGNOSTICO_SECRETO','motivo_consulta'=>'MOTIVO_CLINICO']);
        $doc=$a->documents()->create(['actor_user_id'=>$this->actor->id,'type'=>'PAYMENT_PROOF','purpose'=>'REEVALUATION_ORDER','label'=>'ORDEN_PRIVADA','private_path'=>'private/sensitive.pdf','mime'=>'application/pdf']);
        $result=$this->detail($a->id)->assertOk()->assertJsonPath('appointment.tiempos.llegada',null)->assertJsonCount(0,'documents');
        foreach(['DIAGNOSTICO_SECRETO','MOTIVO_CLINICO','ORDEN_PRIVADA','sensitive.pdf','private_path','evidence_document_id'] as $secret)$this->assertStringNotContainsString($secret,$result->getContent());
    }
    public function test_all_six_mvp_role_names_use_effective_capabilities_without_role_bypass():void
    {
        foreach(['ADMINISTRADOR','ADMISION','COMERCIAL','RECEPCION','CAJA','FACTURACION'] as $role){
            $reader=$this->agendaReader($role);$this->actingAs($reader);
            $this->get(route('scheduling.mvp.admission'))->assertOk();$this->feed()->assertOk();
            $a=$this->lifecycleAppointment($reader,$this->catalog);
            $this->care($a,'EN_ESPERA')->assertForbidden();
            $reader->givePermissionTo(Permission::findOrCreate(C::UPDATE,'web'));
            $this->care($a,'EN_ESPERA')->assertOk();
        }
    }
    public function test_missing_capabilities_block_page_feed_detail_and_care_without_writes():void
    {
        $a=$this->appointment();$no=$this->createUserWithRole('SIN_CAPACIDADES');$this->actingAs($no);
        $this->get(route('scheduling.mvp.admission'))->assertForbidden();$this->feed()->assertForbidden();$this->detail($a->id)->assertForbidden();$this->care($a,'EN_ESPERA')->assertForbidden();
        $this->actingAs($this->actor);$this->care($a,'EN_ESPERA')->assertForbidden();$this->assertDatabaseCount('appointment_operations',0);
    }
    public function test_care_progression_is_idempotent_audited_and_keeps_financial_history_intact():void
    {
        $this->updatePermission();$a=$this->appointment(['total_pagado'=>50,'estado_pagado'=>'PARCIAL','saldo_pendiente'=>50]);
        $a=$a->fresh();$financial=$a->only(['precio_programado','total_pagado','saldo_pendiente','estado_pagado','estado_agenda']);
        foreach(['EN_ESPERA','LLAMANDO','EN_ATENCION','ATENDIDO'] as $target){
            $key=(string)Str::uuid();$this->care($a,$target,$key)->assertOk();$this->care($a,$target,$key)->assertOk();$a=$a->fresh();
            $this->feed()->assertOk()->assertJsonPath('kpis.'.AdmissionStatus::group($a),1);
        }
        foreach(['hora_llegada','hora_llamado','hora_atencion','hora_atendido'] as $field)$this->assertNotNull($a->{$field});
        $this->assertSame($financial,$a->only(array_keys($financial)));$this->assertDatabaseCount('payments',0);
        $this->assertSame(4,$a->events()->where('event_type','ADMISION_ESTADO_ACTUALIZADO')->count());
        $this->care($a,'EN_ESPERA')->assertUnprocessable();
    }
    public function test_pending_closed_wrong_state_and_manipulated_finances_cannot_transition():void
    {
        $this->updatePermission();
        foreach(['PENDIENTE_CONFIRMACION','CONFIRMADA'] as $agenda){$a=$this->appointment(['estado_agenda'=>$agenda]);
            if($agenda==='PENDIENTE_CONFIRMACION')$this->care($a,'EN_ESPERA')->assertUnprocessable();
            $this->care($a,'EN_ESPERA',null,['total_pagado'=>999])->assertUnprocessable();
            $this->care($a,'EN_ATENCION',null,['expected_state'=>'EN_ESPERA'])->assertConflict();
        }
        foreach(['ATENDIDO','CANCELADO','NO_ASISTIO','RETIRO'] as $closed)$this->care($this->appointment(['estado_cita'=>$closed]),'EN_ESPERA')->assertUnprocessable();
        $this->assertDatabaseCount('appointment_operations',0);$this->assertDatabaseCount('payments',0);
    }
    public function test_ocr_payment_confirmation_and_admission_share_exact_same_appointment_and_money():void
    {
        Storage::fake('local');foreach([C::CREATE,C::SUBMIT_PAYMENT,C::UPDATE] as $p)$this->actor->givePermissionTo(Permission::findOrCreate($p,'web'));
        $this->catalog['rate']->update(['nombre'=>'TARIFA ESTANDAR','tipo_tarifa'=>'MONTO_FIJO']);
        $c=Cashier::create(['nombre'=>'Caja integración ficticia','estado'=>'ACTIVO']);
        CashierShift::create(['cashier_id'=>$c->id,'user_id'=>$this->actor->id,'monto_apertura'=>0,'abierto_en'=>now(),'estado'=>'ABIERTO']);
        VoucherSerie::create(['cashier_id'=>$c->id,'tipo_comprobante'=>'TICKET','serie'=>'TNIC','correlativo_actual'=>0,'estado'=>'ACTIVO']);
        $date=$this->catalog['schedule']->fecha_cita;
        $payload=['request_key'=>(string)Str::uuid(),'mode'=>'RESERVE','booking_type'=>'REGULAR','patient_id'=>$this->createPatient($this->actor)->id,
            'doctor_id'=>$this->catalog['doctor']->id,'service_id'=>$this->catalog['service']->id,'fecha_cita'=>$date,'hora_cita'=>'08:00','duracion_cita'=>30];
        $id=$this->postJson(route('scheduling.mvp.agenda.registrations'),$payload)->assertCreated()->json('appointment.appointment_id');
        $this->feed(['fecha'=>$date])->assertOk()->assertJsonPath('kpis.reservas',1)->assertJsonPath('totals.pagos_reales','0.00');
        $key=(string)Str::uuid();$pay=['request_key'=>$key,'payment'=>['amount'=>'50','method'=>'YAPE','operation'=>'QA-NICOLE-1'],
            'proof_review'=>['reviewed'=>true,'source'=>'BROWSER_OCR','date'=>'2026-10-14','time'=>'09:35']];
        for($i=0;$i<2;$i++)$this->post(route('scheduling.mvp.agenda.payments',$id),['payload'=>json_encode($pay),
            'proofs'=>[new UploadedFile(base_path('tests/Fixtures/receipts/yape.png'),'yape.png',null,null,true)]],['Accept'=>'application/json'])->assertOk();
        $this->feed(['fecha'=>$date])->assertOk()->assertJsonPath('kpis.reservas',1)->assertJsonPath('totals.pagos_reales','50.00');
        $this->postJson(route('scheduling.mvp.agenda.payments',$id),['request_key'=>(string)Str::uuid(),'confirm'=>true])->assertOk();
        $this->feed(['fecha'=>$date])->assertOk()->assertJsonPath('kpis.por_llegar',1)->assertJsonPath('totals.pagos_reales','50.00')->assertJsonPath('totals.saldo','50.00');
        $this->detail($id)->assertOk()->assertJsonCount(1,'documents')->assertJsonPath('appointment.economy.fuente','PAYMENTS');
        $this->care(Appointment::findOrFail($id),'EN_ESPERA')->assertOk();
        $this->feed(['fecha'=>$date])->assertOk()->assertJsonPath('kpis.en_espera',1)->assertJsonPath('kpis.por_llegar',0);
        $this->assertDatabaseCount('appointments',1);$this->assertDatabaseCount('payments',1);$this->assertDatabaseCount('appointment_documents',1);
        $this->assertEquals(50,Payment::sum('monto'));
    }
}
