<?php
namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\Doctor;
use App\Models\User;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Direct-return event ledger, including production hotfix history. Never a clinical indication. */
final class ManualReevaluationService
{
    public const MARKED = 'REEVALUACION_MANUAL_MARCADA';
    public const CLEARED = 'REEVALUACION_MANUAL_DESMARCADA';

    public function canUpdate(User $actor): bool
    {
        // All active COMERCIAL users may manage this isolated flag without appointment.update.
        // Care transitions and unrelated appointment updates still require their own permissions.
        return (bool) $actor->is_active && $actor->can(C::MVP_ACCESS) && $actor->can(C::VIEW)
            && ($actor->hasRole('COMERCIAL')
                || ($actor->can(C::UPDATE) && $actor->hasAnyRole(['ADMINISTRADOR', 'ADMISION'])));
    }

    public function eligible(Appointment $a): bool
    {
        if(!in_array($a->estado_agenda,['LEGADO','CONFIRMADA'],true)) return false;
        // Keep the old direct mark for attended appointments. During an open
        // medical episode, the physician must first indicate reevaluation.
        // If procedures were ordered, their dedicated verification/send flow
        // is mandatory; the direct mark must not bypass pending tests.
        return $a->estado_cita==='ATENDIDO'
            || ($a->estado_cita==='EN_ATENCION'
                && AppointmentReevaluationService::indicated($a)
                && !$a->hora_salida
                && (int)($a->operationalReview?->requested_count??0)===0);
    }

    /** One query for the full filtered set; order by event ID, even within one second. */
    public function load(Collection $appointments): void
    {
        $latest = AppointmentEvent::query()->whereIn('id', function ($q) use ($appointments) {
            $q->from('appointment_events')->selectRaw('MAX(id)')
                ->whereIn('appointment_id', $appointments->pluck('id'))
                ->whereIn('event_type', [self::MARKED, self::CLEARED])->groupBy('appointment_id');
        })->with('actor:id,name')->get()->keyBy('appointment_id');
        foreach ($appointments as $a) $a->setRelation('manualReevaluationEvent', $latest->get($a->id));
        AppointmentReevaluationService::loadIndications($appointments);
    }

    public function state(Appointment $a): array
    {
        $event = $a->relationLoaded('manualReevaluationEvent') ? $a->getRelation('manualReevaluationEvent') : null;
        return ['marked' => $event?->event_type === self::MARKED, 'version' => (int) ($event?->id ?? 0),
            'at' => $event?->occurred_at?->toISOString(),
            'actor' => $event ? ['id'=>(int)$event->actor_user_id,'name'=>$event->actor?->name ?? 'Usuario no disponible'] : null];
    }

    public function set(int $id, User $actor, array $data): Appointment
    {
        abort_unless($this->canUpdate($actor), 403);
        $original = Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->firstOr(fn () => abort(404));
        $hash = hash('sha256', json_encode(['MANUAL_REEVALUATION_V1', $id,
            (bool) $data['marked'], (bool) $data['expected_marked'], (int) $data['expected_version']]));
        return DB::transaction(function () use ($id, $actor, $data, $original, $hash) {
            DB::table('appointment_operations')->upsert([['actor_user_id'=>$actor->id,
                'request_key'=>$data['request_key'], 'payload_hash'=>$hash, 'created_at'=>now(), 'updated_at'=>now()]],
                ['actor_user_id', 'request_key'], ['request_key']);
            $op = DB::table('appointment_operations')->where('actor_user_id', $actor->id)
                ->where('request_key', $data['request_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($op->payload_hash, $hash), 409, 'El identificador ya corresponde a otra operación.');
            // A retry acknowledges the operation without replaying it over a later toggle.
            if ($op->appointment_id) {
                $a = Appointment::visibleToAgendaUser($actor->id)->whereKey($op->appointment_id)->firstOr(fn () => abort(404));
                $this->load(collect([$a]));
                return $a;
            }
            Doctor::whereKey($original->doctor_id)->lockForUpdate()->firstOrFail();
            $a = Appointment::visibleToAgendaUser($actor->id)->whereKey($id)->lockForUpdate()->firstOr(fn () => abort(404));
            abort_if((int) $a->doctor_id !== (int) $original->doctor_id, 409, 'La cita cambió. Actualiza antes de continuar.');
            abort_unless($this->eligible($a), 422, 'Solo se permite reevaluación manual en una cita ATENDIDO con agenda confirmada o heredada.');
            // Current read after the appointment lock also works at MySQL REPEATABLE READ.
            $event = $a->events()->whereIn('event_type', [self::MARKED, self::CLEARED])->orderByDesc('id')->lockForUpdate()->first();
            $a->setRelation('manualReevaluationEvent', $event);
            $state = $this->state($a);
            abort_if($state['marked'] !== (bool) $data['expected_marked'] || $state['version'] !== (int) $data['expected_version'],
                409, 'La marca de reevaluación cambió. Actualiza antes de continuar.');
            abort_if($state['marked'] === (bool) $data['marked'], 422, 'No hay cambio de marca para guardar.');
            $event = app(AppointmentHistory::class)->record($a, $data['marked'] ? self::MARKED : self::CLEARED, $actor->id,
                ['metadata'=>['source'=>'ADMISSION_MANUAL_V1', 'operation_id'=>$op->id,
                    'previous_event_id'=>$state['version'], 'from'=>$state['marked'], 'to'=>(bool) $data['marked']]]);
            DB::table('appointment_operations')->where('id', $op->id)->update(['appointment_id'=>$id, 'updated_at'=>now()]);
            $a->setRelation('manualReevaluationEvent', $event);
            return $a;
        }, 3);
    }
}
