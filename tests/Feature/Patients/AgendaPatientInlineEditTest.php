<?php
namespace Tests\Feature\Patients;

use App\Http\Controllers\Patients\OperationalPatientController;
use App\Services\Patients\PatientRecordRevision;

class AgendaPatientInlineEditTest extends \Tests\Support\AgendaV2TestCase
{
    private function url():string{return '/patients/'.$this->a->patient_id;}
    private function data():array
    {
        return $this->a->patient->only(['tipo_identificacion','numero_identidad','nombre','apellido_paterno','apellido_materno','genero'])
            + ['appointment_id'=>$this->a->id,'expected_revision'=>PatientRecordRevision::for($this->a->patient->fresh()),'nombre'=>'Actualizado'];
    }
    public function test_inline_form_uses_shared_native_blade_and_same_update_endpoint_with_audit():void
    {
        $this->getJson($this->url().'/editor?appointment_id='.$this->a->id)->assertOk()->assertJsonPath('can_write',true);
        $id=$this->a->patient_id;$data=$this->data();$data['nombre']='ACTUALIZADO';
        $this->putJson($this->url(),$data)->assertOk()->assertJsonPath('patient.nombre','ACTUALIZADO');
        $this->assertSame($id,$this->a->fresh()->patient_id);$this->assertSame(1,$this->a->events()->where('event_type','FICHA_PACIENTE_CORREGIDA')->count());
        $this->getJson($this->url())->assertOk()->assertJsonPath('patient.nombre','ACTUALIZADO');$this->assertDatabaseCount('payments',0);
    }
    public function test_concurrent_master_or_responsible_edit_rejects_stale_snapshot_without_erasing_data():void
    {
        $data=$this->data();$this->a->patient->update(['email'=>'other@example.invalid']);
        $this->putJson($this->url(),$data)->assertConflict();$this->assertSame('other@example.invalid',$this->a->patient->fresh()->email);
        $data=$this->data();$this->a->patient->responsibles()->create(['tipo_identificacion'=>'DNI','numero_identidad'=>'77777111','nombres'=>'Adulto ficticio','telefono'=>'999777555','parentezco'=>'PAPA','estado'=>'ACTIVO']);
        $this->putJson($this->url(),$data)->assertConflict();
    }
    public function test_unique_document_email_and_minor_rules_are_identical_to_full_maintenance():void
    {
        $other=$this->createPatient($this->admin,['numero_identidad'=>'75555333','historia_clinica'=>'UNIQUE-QA']);
        $data=$this->data();$data['numero_identidad']=$other->numero_identidad;$this->putJson($this->url(),$data)->assertUnprocessable()->assertJsonValidationErrors('numero_identidad');
        $data=$this->data();$data['email']='bad';$this->putJson($this->url(),$data)->assertUnprocessable()->assertJsonValidationErrors('email');
        $data=$this->data();$data['fecha_nacimiento']=now()->subYears(10)->format('Y-m-d');$this->putJson($this->url(),$data)->assertUnprocessable()->assertJsonValidationErrors('registrar_responsable');
        $this->assertSame(0,$this->a->events()->count());
    }
    public function test_creator_and_existing_administrator_can_correct_master_without_allowing_operational_non_creators():void
    {
        $this->a->update(['estado_agenda'=>'CONFIRMADA']);
        foreach([$this->admin,$this->admission,$this->commercial]as$user){$this->actingAs($user);$allowed=$user->hasRole('ADMINISTRADOR');$this->getJson($this->url().'/editor?appointment_id='.$this->a->id)->assertOk()->assertJsonPath('can_write',$allowed);$data=$this->data();$data['nombre']='Rol '.$user->id;$data['patient_id']=999999;$response=$this->putJson($this->url(),$data);$allowed?$response->assertOk():$response->assertForbidden();$this->assertSame($this->a->patient_id,$this->a->fresh()->patient_id);}
        $this->commercial->forceFill(['is_active'=>false])->save();$this->putJson($this->url(),$this->data())->assertUnauthorized();
    }
    public function test_editor_requires_visible_matching_appointment_and_version_for_writes():void
    {
        $this->a->update(['estado_agenda'=>'PENDIENTE_CONFIRMACION','responsible_user_id'=>$this->admin->id]);
        $this->actingAs($this->commercial)->getJson($this->url().'/editor?appointment_id='.$this->a->id)->assertNotFound();$this->putJson($this->url(),$this->data())->assertNotFound();
        $this->actingAs($this->admin);$data=$this->data();unset($data['expected_revision']);$this->putJson($this->url(),$data)->assertUnprocessable();
    }
}
