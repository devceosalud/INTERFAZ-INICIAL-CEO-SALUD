<?php

namespace App\Models;

use App\Support\Scheduling\AppointmentAgendaLifecycle;
use App\Support\Scheduling\AppointmentVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    public function events() { return $this->hasMany(AppointmentEvent::class); }
    public function save(array $options = [])
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($options) {
            if ($this->exists) {
                $ids = array_filter([$this->getOriginal('doctor_id'), $this->doctor_id]);
                Doctor::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                $current = static::whereKey($this->id)->lockForUpdate()->firstOrFail();
                if (!in_array($current->doctor_id, $ids)) { throw new \RuntimeException('La cita cambió de médico; actualiza antes de modificar.'); }
                if ($current->estado_cita === 'RETIRO' && $this->isDirty(['estado_cita', 'fecha_cita', 'hora_cita', 'patient_id', 'doctor_id',
                    'service_id', 'responsible_user_id', 'user_id', 'precio_programado', 'total_pagado', 'tipo_agendamiento', 'estado_agenda'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['appointment' => 'RETIRO conserva la cita original. Usa el flujo de nueva cita/crédito/devolución.']);
                }
            }
            return parent::save($options);
        });
    }
    protected static function booted()
    {
        static::updating(function (Appointment $a) {
            if ($a->isDirty('estado_cita') && $a->estado_cita === 'RETIRO' && !$a->events()->where('event_type', 'RETIRO')->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['appointment' => 'Registra RETIRO mediante el flujo con motivo, actor y presencia.']);
            }
        });
    }
    use HasFactory;

    protected $fillable = [
        'numero_cita',
        'site_id',
        'user_id',
        'responsible_user_id',
        'updated_by_user_id',
        'patient_id',
        'doctor_id',
        'service_id',
        'additional_rate_id',
        'fecha_cita',
        'hora_cita',
        'duracion_cita',
        'motivo_consulta',
        'turno_cita',
        'precio_programado',
        'total_pagado',
        'saldo_pendiente',
        'metodo_pago',
        'es_exonerado',
        'autorizado_por',
        'estado_pagado',
        'numero_operacion',
        'estado_cita',
        'estado_agenda',
        'tipo_agendamiento',
        'observaciones',
        'fecha_registro',
        'economic_source',
    ];

    /** Opt-in scope; it does not change existing readers automatically. */
    public function scopeVisibleToAgendaUser(Builder $query, int $actorId): Builder
    {
        AppointmentVisibility::apply($query, $actorId, $query->getModel()->getTable());

        return $query;
    }

    public function scopeConsumingRegularSlot(Builder $query): Builder
    {
        AppointmentAgendaLifecycle::applyRegularSlotOccupancy($query, $query->getModel()->getTable());

        return $query;
    }

    public function scopeOccupyingInterval(Builder $query): Builder
    {
        AppointmentAgendaLifecycle::applyIntervalOccupancy($query, $query->getModel()->getTable());
        return $query;
    }

    /**
     * Obtiene el usuario (personal del sistema) que registró la cita.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtiene la sede donde se atiende la cita. Nula en citas heredadas.
     */
    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Obtiene el responsable actual de gestión de la cita, distinto del creador.
     */
    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function documents() { return $this->hasMany(AppointmentDocument::class); }

    /**
     * Obtiene el último usuario que modificó la cita, sin reemplazar al creador.
     */
    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * Obtiene el paciente asignado a la cita.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Obtiene el médico encargado de atender la cita.
     */
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Obtiene el servicio médico programado en la cita.
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }


    /**
     * Obtiene la tarifa adicional aplicada a la cita.
     */
    public function additionalRate()
    {
        return $this->belongsTo(AdditionalRate::class, 'additional_rate_id');
    }
}
