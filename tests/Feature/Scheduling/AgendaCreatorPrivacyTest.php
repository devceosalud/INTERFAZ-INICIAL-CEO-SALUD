<?php
namespace Tests\Feature\Scheduling;

use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAgendaLifecycleData;
use Tests\TestCase;

class AgendaCreatorPrivacyTest extends TestCase
{
    use RefreshDatabase, BuildsAgendaLifecycleData;
    public function test_only_administrators_receive_creator_for_visible_appointments_in_all_views():void
    {
        config(['scheduling.enabled'=>true]);
        $creator=$this->agendaReader('ADMINISTRADOR');$owner=$this->agendaReader('COMERCIAL');$catalog=$this->createAppointmentCatalog();
        $a=$this->lifecycleAppointment($creator,$catalog,['fecha_cita'=>$catalog['schedule']->fecha_cita,'estado_agenda'=>'CONFIRMADA','responsible_user_id'=>$owner->id]);
        $a->forceFill(['hora_llegada'=>now()])->save();
        foreach(['ADMINISTRADOR','ADMISION','COMERCIAL','RECEPCION'] as $role){
            $actor=$this->agendaReader($role);$this->actingAs($actor);
            foreach(['dia','semana','mes'] as $view){
                $r=$this->getJson('/scheduling-mvp/agenda/feed?'.http_build_query(['vista'=>$view,'fecha'=>substr($a->fecha_cita,0,10),'doctor_id'=>[$catalog['doctor']->id]]))->assertOk();
                if($view==='mes'){$this->assertStringNotContainsString($creator->name,$r->getContent());continue;}
                $event=collect($r->json('eventos'))->firstWhere('id','cita-'.$a->id);$this->assertNotNull($event);
                $this->assertSame($owner->name,$event['extendedProps']['responsable']);
                if($role==='ADMINISTRADOR'){$this->assertSame($creator->name,$event['extendedProps']['creador']);$this->assertSame($creator->id,$event['extendedProps']['creator_user_id']);}
                else{$this->assertArrayNotHasKey('creador',$event['extendedProps']);$this->assertArrayNotHasKey('creator_user_id',$event['extendedProps']);$this->assertStringNotContainsString($creator->name,$r->getContent());}
            }
        }
    }
    public function test_admin_has_no_exception_to_assigned_or_unassigned_private_reservations():void
    {
        config(['scheduling.enabled'=>true]);$admin=$this->agendaReader('ADMINISTRADOR');$owner=$this->agendaReader();$other=$this->agendaReader();$catalog=$this->createAppointmentCatalog();
        $assigned=$this->lifecycleAppointment($admin,$catalog,['fecha_cita'=>$catalog['schedule']->fecha_cita,'estado_agenda'=>'PENDIENTE_CONFIRMACION','responsible_user_id'=>$owner->id]);
        $unassigned=$this->lifecycleAppointment($owner,$catalog,['fecha_cita'=>$catalog['schedule']->fecha_cita,'estado_agenda'=>'PENDIENTE_CONFIRMACION','responsible_user_id'=>null]);
        foreach([$admin,$owner,$other] as $actor){$this->actingAs($actor);foreach(['dia','semana','mes'] as $view){
            $r=$this->getJson('/scheduling-mvp/agenda/feed?'.http_build_query(['vista'=>$view,'fecha'=>substr($assigned->fecha_cita,0,10),'doctor_id'=>[$catalog['doctor']->id]]))->assertOk();
            $ids=collect($r->json('eventos'))->pluck('extendedProps.appointment_id')->filter()->all();
            if($actor->id===$owner->id && $view!=='mes'){$this->assertContains($assigned->id,$ids);$this->assertContains($unassigned->id,$ids);}
            else{$this->assertNotContains($assigned->id,$ids);$this->assertNotContains($unassigned->id,$ids);$this->assertStringNotContainsString($assigned->patient->nombre.' '.$assigned->patient->numero_identidad,$r->getContent());}
        }}
        $this->assertDatabaseCount('payments',0);$this->assertDatabaseCount('appointment_operations',0);
    }
    public function test_month_keeps_withdrawal_history_anonymous_but_day_preserves_authorized_details(): void
    {
        config(['scheduling.enabled' => true]);
        $actor = $this->agendaReader('ADMINISTRADOR');
        $catalog = $this->createAppointmentCatalog();
        $appointment = $this->lifecycleAppointment($actor, $catalog, [
            'fecha_cita' => $catalog['schedule']->fecha_cita, 'estado_cita' => 'RETIRO',
        ]);
        $this->actingAs($actor);
        $query = ['fecha' => substr($appointment->fecha_cita, 0, 10), 'doctor_id' => [$catalog['doctor']->id]];
        $day = $this->getJson('/scheduling-mvp/agenda/feed?'.http_build_query($query + ['vista' => 'dia']))->assertOk();
        $day->assertJsonPath('retiros.0.appointment_id', $appointment->id);
        $month = $this->getJson('/scheduling-mvp/agenda/feed?'.http_build_query($query + ['vista' => 'mes']))->assertOk();
        $month->assertJsonPath('retiros', []);
        $this->assertStringNotContainsString($appointment->patient->numero_identidad, $month->getContent());
        $this->assertStringNotContainsString($actor->name, $month->getContent());
        $this->assertStringNotContainsString('"patient":', $month->getContent());
    }

    public function test_history_cannot_reveal_creator_even_with_audit_permission_and_keeps_stored_audit(): void
    {
        config(['scheduling.enabled' => true]);
        $creator = $this->agendaReader('ADMINISTRADOR'); $other = $this->agendaReader();
        $catalog = $this->createAppointmentCatalog();
        $a = $this->lifecycleAppointment($creator, $catalog, ['fecha_cita' => $catalog['schedule']->fecha_cita, 'estado_agenda' => 'CONFIRMADA', 'responsible_user_id' => null]);
        $history = app(\App\Services\Scheduling\AppointmentHistory::class);
        foreach (['RESERVA_CREADA', 'CITA_CREADA', 'PRECIO_REGISTRADO', 'REGISTRO_OPERATIVO', 'REPROGRAMADA'] as $event) {
            $history->record($a, $event, $creator->id);
        }
        $history->record($a, 'CONTACTO_PACIENTE_ACTUALIZADO', $other->id);
        foreach (['ADMINISTRADOR', 'ADMISION', 'COMERCIAL', 'RECEPCION'] as $role) {
            $actor = $this->agendaReader($role);
            $actor->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate(C::VIEW_AUDIT, 'web'));
            $r = $this->actingAs($actor)->getJson('/scheduling-mvp/agenda/appointments/'.$a->id.'/history')->assertOk();
            $events = collect($r->json('events'));
            $this->assertCount(6, $events);
            $this->assertSame($other->name, $events->firstWhere('type', 'CONTACTO_PACIENTE_ACTUALIZADO')['actor']);
            foreach (['RESERVA_CREADA', 'CITA_CREADA', 'PRECIO_REGISTRADO', 'REGISTRO_OPERATIVO', 'REPROGRAMADA'] as $event) {
                $this->assertSame($role === 'ADMINISTRADOR' ? $creator->name : null, $events->firstWhere('type', $event)['actor']);
            }
            if ($role !== 'ADMINISTRADOR') $this->assertStringNotContainsString($creator->name, $r->getContent());
            $admission = $this->getJson('/scheduling-mvp/admission/appointments/'.$a->id)->assertOk();
            $admission->assertJsonPath('appointment.responsable', null);
            foreach ($admission->json('events') as $event) {
                $this->assertSame($event['type'] === 'CONTACTO_PACIENTE_ACTUALIZADO' ? $other->name
                    : ($role === 'ADMINISTRADOR' ? $creator->name : null), $event['actor']);
            }
            if ($role !== 'ADMINISTRADOR') $this->assertStringNotContainsString($creator->name, $admission->getContent());
        }
        $this->assertSame(5, $a->events()->where('actor_user_id', $creator->id)->count());
    }

}