<?php
namespace Tests\Feature\Admin;

use App\Models\{Doctor,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

/** Same legacy HTTP payloads; a doctor's login identity is irrelevant to catalogue CRUD. */
class DoctorCatalogIdentityCompatibilityTest extends TestCase
{
    use RefreshDatabase,BuildsAgendaLifecycleData;
    protected function setUp():void{parent::setUp();\Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-10-21 09:00','America/Lima'));}
    protected function tearDown():void{\Carbon\Carbon::setTestNow();parent::tearDown();}
    public static function links():array{return ['unlinked'=>[false],'linked'=>[true]];}
    /** @dataProvider links */
    public function test_existing_doctor_crud_keeps_ids_and_appointments_with_or_without_a_personal_login(bool $linked):void
    {
        $admin=$this->agendaReader('ADMINISTRADOR');$catalog=$this->createAppointmentCatalog();$holder=$this->createUser();
        if($linked)$holder->forceFill(['doctor_id'=>$catalog['doctor']->id])->save();
        $history=$this->lifecycleAppointment($admin,$catalog,['estado_agenda'=>'LEGADO','estado_cita'=>'ATENDIDO','fecha_cita'=>'2025-10-10']);
        $before=$history->fresh()->getAttributes();$users=User::count();$originalId=$catalog['doctor']->id;
        $this->actingAs($admin)->postJson('/master/admin/doctor/store',[
            'nombres'=>'NUEVO MEDICO FICTICIO CATALOGO','cmp'=>'CMP-FICTICIO','rne'=>'RNE-FICTICIO','specialty_id'=>$catalog['specialty']->id])
            ->assertOk()->assertJsonPath('code',1);
        $this->assertSame(2,Doctor::count());$this->assertSame($users,User::count());
        $this->putJson('/master/admin/doctor/update',['doctor_id_edit'=>$originalId,'nombres_edit'=>'MEDICO FICTICIO EDITADO',
            'cmp_edit'=>'CMP-FICTICIO-EDIT','rne_edit'=>'RNE-FICTICIO-EDIT','specialty_id_edit'=>$catalog['specialty']->id])
            ->assertOk()->assertJsonPath('code',1);
        $this->assertSame($originalId,$catalog['doctor']->fresh()->id);$this->assertSame('MEDICO FICTICIO EDITADO',$catalog['doctor']->fresh()->nombre);
        $this->postJson('/master/admin/doctor/delete',['id'=>$originalId])->assertOk()->assertJsonPath('code',1);
        $this->assertSame('INACTIVO',$catalog['doctor']->fresh()->estado);$this->assertSame(2,Doctor::count());
        $this->assertSame($users,User::count());$this->assertSame($before,$history->fresh()->getAttributes());
        $this->assertSame($linked?$originalId:null,$holder->fresh()->getAttribute('doctor_id'));
    }
}
