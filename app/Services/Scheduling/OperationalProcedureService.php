<?php

namespace App\Services\Scheduling;

use App\Models\Appointment;
use App\Models\User;
use App\Support\Scheduling\SchedulingCapability as C;
use Illuminate\Support\Facades\DB;

/** Same appointment, same day. Never writes clinical states, money or booking data. */
final class OperationalProcedureService
{
    public const CUSTOM_MAX = 500;
    public const SUPERVISE = 'appointment.operational_reevaluation.supervise';
    public const CATALOG = [
        'radiography' => 'Radiografía', 'ultrasound' => 'Ecografía',
        'laboratory' => 'Laboratorio', 'wound_care' => 'Curación',
        'mri' => 'Resonancia magnética', 'prp' => 'PRP',
        'hyaluronic_acid' => 'Ácido hialurónico',
    ];

    public function canSelect(User $u): bool { return $this->eligible($u) && $u->hasRole('ADMINISTRADOR'); }
    public function canComplete(User $u): bool { return $this->eligible($u) && $u->hasAnyRole(['ADMINISTRADOR', 'ADMISION']); }
    public function canSend(User $u): bool
    {
        return $this->eligible($u) && ($u->hasRole('ADMISION')
            || ($u->hasRole('ADMINISTRADOR') && $u->can(self::SUPERVISE)));
    }
    private function eligible(User $u): bool { return $u->is_active && $u->can(C::MVP_ACCESS) && $u->can(C::VIEW); }

    public static function active(Appointment $a): bool
    {
        return substr($a->fecha_cita, 0, 10) === now('America/Lima')->toDateString()
            && in_array($a->estado_agenda, ['LEGADO', 'CONFIRMADA'], true)
            && !in_array($a->estado_cita, ['CANCELADO', 'RETIRO', 'NO_ASISTIO'], true) && !$a->hora_salida;
    }

    public static function ready(Appointment $a): bool
    {
        return $a->relationLoaded('operationalReview') && self::status($a) === 'READY';
    }

    /** A cached READY flag is insufficient: origin and current progress must agree. */
    public static function status(Appointment $a): string
    {
        $review=$a->operationalReview;
        if (!$review) return 'NOT_READY';
        if ($review->status === 'WITHDRAWN') return 'WITHDRAWN';
        if (!self::active($a)) return 'INACTIVE';
        if ($review->status !== 'READY') return $review->status;
        if ($review->origin === 'MANUAL') {
            return $review->decided_by_user_id && $review->completed_count > 0 ? 'READY' : 'SUSPENDED';
        }
        return $review->origin === 'AUTOMATIC' && $review->requested_count > 0
            && $review->completed_count === $review->requested_count ? 'READY' : 'NOT_READY';
    }

    public function detail(Appointment $a, User $u): array
    {
        $a->loadMissing('operationalReview.decidedBy');
        $items = DB::table('appointment_requested_procedures')->where('appointment_id', $a->id)
            ->where('selected', true)->orderBy('id')->get();
        $people=\App\Models\User::whereIn('id',$items->pluck('requested_by_user_id')->merge($items->pluck('verified_by_user_id'))->filter()->unique())->pluck('name','id');
        $review = $a->operationalReview;
        $done=$items->whereNotNull('completed_at')->count();
        $status=self::status($a);
        return [
            'version' => $review?->version ?? 0,
            'requested_count' => $items->count(), 'completed_count' => $done,
            'pending_count' => $items->count()-$done,
            'catalog' => $this->canSelect($u) ? self::CATALOG : [],
            'can_select' => $this->canSelect($u), 'can_complete' => $this->canComplete($u),
            'can_send' => $this->canSend($u) && self::active($a) && $items->whereNotNull('completed_at')->isNotEmpty()
                && (!AppointmentReevaluationService::indicated($a) || AppointmentReevaluationService::confirmed($a)),
            'can_withdraw' => $this->canSend($u) && $status === 'READY',
            'items' => $items->map(fn ($p) => ['key' => $p->procedure_key,
                'label' => $p->custom_label ?? self::CATALOG[$p->procedure_key] ?? 'Procedimiento histórico', 'custom' => $p->custom_label !== null,
                'requested_by' => $people[$p->requested_by_user_id] ?? 'Usuario no disponible',
                'completed_by' => $p->verified_by_user_id ? ($people[$p->verified_by_user_id] ?? 'Usuario no disponible') : null, 'completed' => $p->completed_at !== null,
                'requested_at' => $p->requested_at, 'completed_at' => $p->completed_at])->all(),
            'review' => ['status' => $status,
                'origin' => $review?->origin, 'ready_at' => $review?->ready_at?->toISOString(), 'reason' => $review?->reason,
                'decision_by' => $review?->decided_by_user_id ? ['id'=>(int)$review->decided_by_user_id,'name'=>$review->decidedBy?->name ?? 'Usuario no disponible'] : null,
                'authorized_by' => $review?->origin === 'MANUAL' && in_array($status,['READY','SUSPENDED'],true) && $review->decided_by_user_id
                    ? ['id'=>(int)$review->decided_by_user_id,'name'=>$review->decidedBy?->name ?? 'Usuario no disponible'] : null],
        ];
    }

    public function mutate(int $id, User $u, string $action, array $data): void
    {
        abort_unless(match ($action) {
            'selection' => $this->canSelect($u), 'completion' => $this->canComplete($u),
            'send', 'withdraw' => $this->canSend($u), default => false,
        }, 403);
        Appointment::visibleToAgendaUser($u->id)->whereKey($id)->firstOr(fn () => abort(404));
        DB::transaction(function () use ($id, $u, $action, $data) {
            $hash = hash('sha256', json_encode(['OPERATIONAL_PROCEDURES', $id, $action, $data]));
            DB::table('appointment_operations')->upsert([['actor_user_id' => $u->id, 'request_key' => $data['request_key'],
                'payload_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]], ['actor_user_id', 'request_key'], ['request_key']);
            $op = DB::table('appointment_operations')->where('actor_user_id', $u->id)->where('request_key', $data['request_key'])->lockForUpdate()->first();
            abort_unless(hash_equals($op->payload_hash, $hash), 409, 'La solicitud ya corresponde a otra operación.');
            $a = Appointment::visibleToAgendaUser($u->id)->whereKey($id)->lockForUpdate()->firstOr(fn () => abort(404));
            if ($op->appointment_id) return;
            $review = DB::table('appointment_operational_reviews')->where('appointment_id', $id)->lockForUpdate()->first();
            abort_unless((int) ($review?->version ?? 0) === $data['expected_version'], 409, 'Los procedimientos cambiaron. Actualiza antes de guardar.');
            if (!$review) {
                DB::table('appointment_operational_reviews')->insert(['appointment_id' => $id, 'version' => 0,
                    'status' => 'NOT_READY', 'created_at' => now(), 'updated_at' => now()]);
            }
            $before = $this->snapshot($id);
            if ($action === 'selection') {
                $keys = $data['procedures'];
                abort_if(array_diff($keys, array_keys(self::CATALOG)), 422, 'Procedimiento desconocido.');
                $existing = DB::table('appointment_requested_procedures')->where('appointment_id', $id)->get()->keyBy('procedure_key');
                // Existing clients editing the seven preset choices must not silently withdraw custom requests.
                $custom = array_key_exists('custom_procedures',$data) ? $data['custom_procedures']
                    : $existing->filter(fn($p)=>$p->selected && $p->custom_label !== null)->map(fn($p)=>['id'=>substr($p->procedure_key,2),'label'=>$p->custom_label])->values()->all();
                $labels=[];
                foreach($custom as $item) {
                    $label=\App\Support\Scheduling\OperationalText::trim($item['label']);
                    \Illuminate\Support\Facades\Validator::make(['label'=>$label],['label'=>'required|string|max:500'],['label.required'=>'Escribe el procedimiento solicitado por el médico.'])->validate();
                    $key='c:'.$item['id'];
                    abort_if(isset($existing[$key]) && $existing[$key]->custom_label!==$label,409,'El nombre de una solicitud confirmada se conserva. Retírala y registra una nueva si necesitas corregirla.');
                    $labels[$key]=$label;$keys[]=$key;
                }
                foreach ($existing as $key => $p) {
                    $selected = in_array($key, $keys, true);
                    if ($selected !== (bool) $p->selected) {
                        DB::table('appointment_requested_procedures')->where('id', $p->id)->update([
                            'selected' => $selected, 'requested_by_user_id' => $u->id, 'requested_at' => now(),
                            'completed_at' => null, 'verified_by_user_id' => null, 'updated_at' => now()]);
                    }
                }
                foreach (array_diff($keys, $existing->keys()->all()) as $key) {
                    DB::table('appointment_requested_procedures')->insert(['appointment_id' => $id, 'procedure_key' => $key,
                        'custom_label' => $labels[$key] ?? null, 'selected' => true, 'requested_by_user_id' => $u->id, 'requested_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                }
            } elseif ($action === 'completion') {
                $p = DB::table('appointment_requested_procedures')->where('appointment_id', $id)
                    ->where('procedure_key', $data['procedure'])->where('selected', true)->first();
                abort_unless($p, 404);
                DB::table('appointment_requested_procedures')->where('id', $p->id)->update([
                    'completed_at' => $data['completed'] ? now() : null,
                    'verified_by_user_id' => $data['completed'] ? $u->id : null, 'updated_at' => now()]);
            }
            $items = DB::table('appointment_requested_procedures')->where('appointment_id', $id)->where('selected', true)->get();
            $done = $items->whereNotNull('completed_at')->count();
            $all = $items->isNotEmpty() && $done === $items->count();
            $changes = ['version' => $data['expected_version'] + 1, 'requested_count' => $items->count(), 'completed_count' => $done, 'updated_at' => now()];
            if ($action === 'send') {
                abort_if(AppointmentReevaluationService::indicated($a)
                    && !AppointmentReevaluationService::confirmed($a),422,
                    'Admisión o Comercial debe confirmar primero la pre-reevaluación.');
                abort_unless($data['confirmed'] && self::active($a) && $done > 0, 422, 'La reevaluación requiere una cita activa de hoy y al menos un procedimiento realizado.');
                abort_if(!$all && trim($data['reason'] ?? '') === '', 422, 'Indica por qué se envía con procedimientos pendientes.');
                if ($review?->status !== 'READY') $changes += ['status' => 'READY', 'origin' => 'MANUAL',
                    'ready_at' => now(), 'decided_by_user_id' => $u->id, 'reason' => trim($data['reason'] ?? '')];
            } elseif ($action === 'withdraw') {
                abort_unless($data['confirmed'] && $review?->status === 'READY' && trim($data['reason'] ?? '') !== '', 422, 'Confirma la retirada e indica el motivo.');
                $changes += ['status' => 'WITHDRAWN', 'reason' => trim($data['reason']), 'decided_by_user_id' => $u->id];
            } elseif ($review?->status !== 'WITHDRAWN') {
                // A manual decision survives changes to the requested set. Zero completed suspends it visibly.
                $manual = $review?->origin === 'MANUAL';
                $status = $manual ? ($done > 0 ? 'READY' : 'SUSPENDED') : ($all && self::active($a) ? 'READY' : 'NOT_READY');
                $changes['status'] = $status;
                if ($status === 'READY' && $review?->status !== 'READY') {
                    $changes['ready_at'] = now();
                    if (!$manual) $changes += ['origin' => 'AUTOMATIC', 'decided_by_user_id' => $u->id, 'reason' => null];
                }
            }
            DB::table('appointment_operational_reviews')->where('appointment_id', $id)->update($changes);
            DB::table('appointment_procedure_events')->insert(['appointment_id' => $id, 'actor_user_id' => $u->id,
                'request_key' => $data['request_key'], 'action' => strtoupper($action), 'before' => json_encode($before),
                'after' => json_encode($this->snapshot($id)), 'occurred_at' => now()]);
            DB::table('appointment_operations')->where('id', $op->id)->update(['appointment_id' => $id, 'updated_at' => now()]);
        }, 3);
    }

    private function snapshot(int $id): array
    {
        return ['review' => DB::table('appointment_operational_reviews')->where('appointment_id', $id)->first(),
            'items' => DB::table('appointment_requested_procedures')->where('appointment_id', $id)->orderBy('id')->get()->all()];
    }

    public function queue(User $u): array
    {
        abort_unless($this->eligible($u), 403);
        return app(AppointmentReevaluationService::class)->queue($u);
    }
}
