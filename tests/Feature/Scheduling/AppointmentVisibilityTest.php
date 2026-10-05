<?php

namespace Tests\Feature\Scheduling;

use App\Models\Appointment;
use App\Support\Scheduling\AppointmentAgendaLifecycle as Lifecycle;
use App\Support\Scheduling\AppointmentVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Concerns\BuildsBaselineData;
use Tests\TestCase;

class AppointmentVisibilityTest extends TestCase
{
    use BuildsBaselineData;
    use RefreshDatabase;

    public function test_opt_in_visibility_matches_for_eloquent_and_query_builder(): void
    {
        [$actor, $appointments] = $this->fixtures();
        $expected = collect($appointments)->except(['pending other', 'pending additional other'])->pluck('id')->all();

        $this->assertSame($expected, Appointment::visibleToAgendaUser($actor->id)->orderBy('id')->pluck('id')->all());
        $this->assertSame($expected, AppointmentVisibility::apply(DB::table('appointments'), $actor->id)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all());

        // No global scope: existing readers are not silently changed by this foundation.
        $this->assertSame(count($appointments), Appointment::count());
    }

    public function test_visibility_is_grouped_and_does_not_bypass_existing_filters(): void
    {
        [$actor, $appointments] = $this->fixtures();
        $target = $appointments['legacy other'];

        $this->assertSame([$target->id], Appointment::whereKey($target->id)->visibleToAgendaUser($actor->id)->pluck('id')->all());
        $this->assertSame([$target->id], AppointmentVisibility::apply(
            DB::table('appointments')->where('appointments.id', $target->id),
            $actor->id
        )->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_direct_queries_can_use_a_table_alias_and_joins(): void
    {
        [$actor, $appointments] = $this->fixtures();
        $expected = collect($appointments)->except(['pending other', 'pending additional other'])->pluck('id')->all();
        $query = DB::table('appointments as agenda')
            ->join('users as creators', 'creators.id', '=', 'agenda.user_id');

        $this->assertSame($expected, AppointmentVisibility::apply($query, $actor->id, 'agenda')
            ->orderBy('agenda.id')->pluck('agenda.id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_scope_rejects_an_unauthenticated_actor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Appointment::visibleToAgendaUser(0)->get();
    }

    private function fixtures(): array
    {
        $actor = $this->createUser();
        $other = $this->createUser();
        $patient = $this->createPatient($actor);
        $catalog = $this->createAppointmentCatalog();
        $appointments = [];

        foreach ([
            'legacy own' => [$actor->id, Lifecycle::LEGACY, null],
            'legacy other' => [$other->id, Lifecycle::LEGACY, null],
            'confirmed regular other' => [$other->id, Lifecycle::CONFIRMED, Lifecycle::REGULAR],
            'confirmed additional other' => [$other->id, Lifecycle::CONFIRMED, Lifecycle::ADDITIONAL],
            'pending own' => [$actor->id, Lifecycle::PENDING_CONFIRMATION, null],
            'pending other' => [$other->id, Lifecycle::PENDING_CONFIRMATION, null],
            'pending additional own' => [$actor->id, Lifecycle::PENDING_CONFIRMATION, Lifecycle::ADDITIONAL],
            'pending additional other' => [$other->id, Lifecycle::PENDING_CONFIRMATION, Lifecycle::ADDITIONAL],
        ] as $name => [$creatorId, $state, $type]) {
            $appointments[$name] = Appointment::create([
                'numero_cita' => 'VISIBILITY-'.count($appointments),
                'user_id' => $creatorId,
                // Assigning responsibility does not grant access to another creator's private row.
                'responsible_user_id' => $actor->id,
                'patient_id' => $patient->id,
                'doctor_id' => $catalog['doctor']->id,
                'service_id' => $catalog['service']->id,
                'additional_rate_id' => $catalog['rate']->id,
                'fecha_cita' => $catalog['schedule']->fecha_cita,
                'hora_cita' => '08:00:00',
                'estado_cita' => 'PROGRAMADO',
                'estado_agenda' => $state,
                'tipo_agendamiento' => $type,
            ]);
        }

        return [$actor, $appointments];
    }
}
