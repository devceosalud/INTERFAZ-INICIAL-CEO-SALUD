<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorSchedule extends Model
{
    use HasFactory;

    public function save(array $options = [])
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($options) {
            $lockedDoctors = array_filter([$this->getOriginal('doctor_id'), $this->doctor_id]);
            Doctor::whereIn('id', $lockedDoctors)->orderBy('id')->lockForUpdate()->get();
            $former = $this->exists ? static::whereKey($this->id)->lockForUpdate()->firstOrFail()->getAttributes() : null;
            if ($former && !in_array($former['doctor_id'], $lockedDoctors)) { throw new \RuntimeException('El horario cambió de médico. Actualiza antes de editar.'); }
            $changed = $this->isDirty(['doctor_id', 'site_id', 'fecha_cita', 'dia_semana', 'hora_inicio', 'hora_fin', 'duracion_cita', 'estado']);
            $saved = parent::save($options);
            if ($saved && $former && $changed) { app(\App\Services\Scheduling\PendingScheduleContingencyService::class)->inspect($former, auth()->id()); }
            return $saved;
        });
    }

    public function delete()
    {
        return \Illuminate\Support\Facades\DB::transaction(function () {
            Doctor::whereKey($this->doctor_id)->lockForUpdate()->firstOrFail();
            $former = $this->getOriginal(); $deleted = parent::delete();
            if ($deleted) { app(\App\Services\Scheduling\PendingScheduleContingencyService::class)->inspect($former, auth()->id()); }
            return $deleted;
        });
    }

    protected $fillable = [
        'doctor_id',
        'site_id',
        'dia_semana',
        'fecha_cita',
        'hora_inicio',
        'hora_fin',
        'duracion_cita',
        'estado'
    ];

    public const DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Sede donde se atiende el bloque. Nula en horarios heredados.
     */
    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
