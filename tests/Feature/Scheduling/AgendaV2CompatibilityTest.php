<?php
namespace Tests\Feature\Scheduling;

use App\Models\AppointmentOperationalReview;
use App\Services\Scheduling\AdmissionTaskService;
use App\Support\Scheduling\AgendaAppointmentPresentation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AgendaV2CompatibilityTest extends \Tests\Support\AgendaV2TestCase
{
    public function test_additive_migration_preserves_legacy_appointment_tasks_and_events():void
    {
        $migration=require database_path('migrations/2026_10_16_020000_create_appointment_operational_procedures.php');
        $followups=require database_path('migrations/2026_10_23_030000_create_medical_procedure_followups.php');
        $order=require database_path('migrations/2026_10_23_040000_preserve_clinical_order_details.php');$order->down();
        $followups->down(); // Empty extension depends on the legacy request table (MariaDB FK).
        $migration->down();
        app(AdmissionTaskService::class)->create($this->a->id,$this->admin,'Observación libre histórica ficticia',(string)Str::uuid());
        $tables=['appointments','appointment_events','appointment_admission_tasks','appointment_admission_task_events','payments','vouchers'];
        $before=[];foreach($tables as $t)$before[$t]=DB::table($t)->orderBy('id')->get()->toJson();
        $migration->up();
        Schema::table('appointment_requested_procedures',fn(\Illuminate\Database\Schema\Blueprint $t)=>$t->string('custom_label',500)->nullable());
        (require database_path('migrations/2026_10_23_010000_add_procedure_request_identity.php'))->up();
        $followups->up();$order->up();
        (require database_path('migrations/2026_10_23_050000_enable_personal_procedures_and_manual_followups.php'))->up(); // Restore the additive dependencies after exercising the legacy table.
        foreach($tables as $t)$this->assertSame($before[$t],DB::table($t)->orderBy('id')->get()->toJson(),$t);
        $this->assertDatabaseCount('appointment_operational_reviews',0);$this->assertDatabaseCount('appointment_requested_procedures',0);
        $this->select()->assertOk();
        try{$migration->down();$this->fail('A populated migration must retain its audit.');}catch(\RuntimeException $e){$this->assertStringContainsString('Conservar procedimientos',$e->getMessage());}
        $this->assertTrue(Schema::hasTable('appointment_procedure_events'));
        $this->assertDatabaseCount('appointment_admission_tasks',1);$this->assertDatabaseCount('appointment_procedure_events',1);
    }
    public function test_critical_financial_colors_win_over_orange_with_visible_readiness_badge():void
    {
        $a=$this->a->fresh();$a->setRelation('operationalReview',new AppointmentOperationalReview(['status'=>'READY','origin'=>'AUTOMATIC','requested_count'=>2,'completed_count'=>2]));
        $position=['authority'=>'PAYMENTS','paid_cents'=>3000,'allocation_required'=>false,'refund_reserved_cents'=>1000];
        $p=AgendaAppointmentPresentation::of($a,$position);
        $this->assertSame('REEMBOLSO_SOLICITADO',$p['clave']);$this->assertContains('LISTO_REEVALUACION',array_column($p['badges'],'clave'));
        $position['refund_reserved_cents']=0;$position['authority']='UNALLOCATED';
        $p=AgendaAppointmentPresentation::of($a,$position);$this->assertNotSame('LISTO_REEVALUACION',$p['clave']);
        $this->assertContains('CONCILIAR_PAGO',array_column($p['badges'],'clave'));$this->assertContains('LISTO_REEVALUACION',array_column($p['badges'],'clave'));
        $a->estado_cita='CANCELADO';$p=AgendaAppointmentPresentation::of($a,$position);$this->assertSame('DESACTIVADA',$p['clave']);
        $this->assertFalse($p['operational_reevaluation_ready']);
    }
    public function test_current_day_uses_lima_at_utc_midnight_and_readers_do_not_write_readiness():void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-17 02:30:00','UTC'));
        $this->a->update(['fecha_cita'=>'2026-10-16']);$this->select(['radiography'])->assertOk();$this->complete()->assertOk();
        $this->queue()->assertOk()->assertJsonCount(1,'rows');
        $before=DB::table('appointment_operational_reviews')->get()->toJson();$this->queue()->assertOk();$this->detail()->assertOk();
        $this->assertSame($before,DB::table('appointment_operational_reviews')->get()->toJson());
        $this->travelTo(\Carbon\Carbon::parse('2026-10-17 05:00:01','UTC'));$this->queue()->assertOk()->assertJsonCount(0,'rows');
        $this->assertSame($before,DB::table('appointment_operational_reviews')->get()->toJson());
    }
    public function test_sensitive_operational_pages_do_not_load_session_replay_analytics():void
    {
        $this->get(route('admissionit.appointment.index'))->assertRedirect(route('scheduling.mvp.agenda'));
        foreach([route('scheduling.mvp.agenda'),route('scheduling.mvp.admission')] as $url){
            $this->get($url)->assertOk()->assertDontSee('www.clarity.ms')->assertDontSee('ywr4tcbik1');
        }
    }
}
