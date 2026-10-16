<?php

namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\User;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Support\Facades\DB;

final class AdmissionTaskService
{
    public const MANAGE = 'appointment.admission_tasks.manage';
    public const COMPLETE = 'appointment.admission_tasks.complete';

    private function eligible(User $actor): bool
    {
        return (bool) $actor->is_active && $actor->can(C::MVP_ACCESS) && $actor->can(C::VIEW);
    }

    public function canManage(User $actor): bool
    {
        return $this->eligible($actor)
            && ($actor->hasRole('ADMINISTRADOR') || $actor->can(self::MANAGE));
    }

    public function canComplete(User $actor): bool
    {
        return $this->eligible($actor)
            && ($this->canManage($actor)
                || $actor->hasAnyRole(['ADMISION', 'COMERCIAL'])
                || $actor->can(self::COMPLETE));
    }

    public function canRead(User $actor): bool
    {
        return $this->canManage($actor) || $this->canComplete($actor);
    }

    private function appointment(int $id, User $actor, bool $lock = false): Appointment
    {
        $query = Appointment::visibleToAgendaUser($actor->id)->whereKey($id);
        if ($lock) $query->lockForUpdate();
        return $query->firstOr(fn () => abort(404));
    }

    public function listForAppointment(Appointment $appointment, User $actor): array
    {
        $canManage = $this->canManage($actor);
        $canComplete = $this->canComplete($actor);
        if (!$canManage && !$canComplete) return [
            'tasks' => [], 'can_manage' => false, 'can_complete' => false,
        ];
        // The caller must have already applied AppointmentVisibility to the appointment.
        $rows = DB::table('appointment_admission_tasks')
            ->where('appointment_id', $appointment->id)
            ->orderBy('id')
            ->get(['id', 'body', 'created_at', 'completed_at', 'created_by_user_id', 'completed_by_user_id']);

        $staff = [];
        if ($canManage) {
            $ids = $rows->flatMap(fn ($r) => [$r->created_by_user_id, $r->completed_by_user_id])->filter()->unique();
            $staff = DB::table('users')->whereIn('id', $ids)->pluck('name', 'id')->all();
        }

        return [
            'tasks' => $rows->map(function ($task) use ($canManage, $staff) {
                $entry = [
                    'id' => (int) $task->id,
                    'text' => $task->body,
                    'completed' => $task->completed_at !== null,
                    'created_at' => $task->created_at,
                    'completed_at' => $task->completed_at,
                ];
                if ($canManage) {
                    $entry['created_by'] = $staff[$task->created_by_user_id] ?? 'Usuario no disponible';
                    $entry['completed_by'] = $task->completed_by_user_id
                        ? ($staff[$task->completed_by_user_id] ?? 'Usuario no disponible') : null;
                }
                return $entry;
            })->all(),
            'can_manage' => $canManage,
            'can_complete' => $canComplete,
        ];
    }

    public function create(int $appointmentId, User $actor, string $body, string $requestKey): void
    {
        abort_unless($this->canManage($actor), 403);
        $body = trim($body);
        abort_unless(mb_strlen($body) >= 3 && mb_strlen($body) <= 1000, 422, 'Escribe una observación operativa de 3 a 1000 caracteres.');
        $hash = hash('sha256', $appointmentId . "\n" . $body);
        DB::transaction(function () use ($appointmentId, $actor, $body, $requestKey, $hash) {
            $this->appointment($appointmentId, $actor, true);
            $now = now();
            $created = DB::table('appointment_admission_tasks')->insertOrIgnore([
                'appointment_id' => $appointmentId,
                'created_by_user_id' => $actor->id,
                'request_key' => $requestKey,
                'payload_hash' => $hash,
                'body' => $body,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $task = DB::table('appointment_admission_tasks')
                ->where('created_by_user_id', $actor->id)
                ->where('request_key', $requestKey)->first();
            abort_unless($task && hash_equals($task->payload_hash, $hash)
                && (int) $task->appointment_id === $appointmentId, 409,
                'La solicitud ya corresponde a otra observación.');
            if ($created) {
                DB::table('appointment_admission_task_events')->insert([
                    'task_id' => $task->id,
                    'actor_user_id' => $actor->id,
                    'action' => 'CREATED',
                    'occurred_at' => $now,
                ]);
            }
        }, 3);
    }

    public function setCompleted(int $appointmentId, int $taskId, User $actor, bool $expected, bool $completed): void
    {
        abort_unless($this->canComplete($actor), 403);
        abort_if($expected === $completed, 422, 'No hay cambio de estado para guardar.');
        DB::transaction(function () use ($appointmentId, $taskId, $actor, $expected, $completed) {
            $this->appointment($appointmentId, $actor, true);
            $task = DB::table('appointment_admission_tasks')
                ->where('appointment_id', $appointmentId)->where('id', $taskId)
                ->lockForUpdate()->first();
            abort_unless($task, 404);
            abort_if(($task->completed_at !== null) !== $expected, 409,
                'Esta tarea cambió. Actualiza el detalle antes de continuar.');
            $now = now();
            DB::table('appointment_admission_tasks')->where('id', $taskId)->update([
                'completed_at' => $completed ? $now : null,
                'completed_by_user_id' => $completed ? $actor->id : null,
                'updated_at' => $now,
            ]);
            DB::table('appointment_admission_task_events')->insert([
                'task_id' => $taskId,
                'actor_user_id' => $actor->id,
                'action' => $completed ? 'COMPLETED' : 'REOPENED',
                'occurred_at' => $now,
            ]);
        }, 3);
    }
}
