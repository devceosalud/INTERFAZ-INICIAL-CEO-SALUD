<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class DoctorService extends Model
{
    use HasFactory;

    /** Keep legacy inactive rows while serializing active creates/reactivations by doctor. */
    public function save(array $options = [])
    {
        if ($this->estado !== 'ACTIVO' || ($this->exists && !$this->isDirty(['doctor_id', 'service_id', 'estado']))) {
            return parent::save($options);
        }

        return $this->getConnection()->transaction(function () use ($options) {
            $doctor = Doctor::on($this->getConnectionName())->whereKey($this->doctor_id)->lockForUpdate()->first();
            if (!$doctor) {
                throw ValidationException::withMessages(['doctor_id' => 'Seleccione un médico existente.']);
            }
            $duplicates = $this->newQuery()->where('doctor_id', $this->doctor_id)
                ->where('service_id', $this->service_id)->where('estado', 'ACTIVO');
            if ($this->exists) { $duplicates->where($this->getKeyName(), '<>', $this->getKey()); }
            // A locking read also sees concurrent commits under MySQL REPEATABLE READ.
            if ($duplicates->lockForUpdate()->first([$this->getKeyName()])) {
                throw ValidationException::withMessages(['service_id' => 'Ya existe una asignación ACTIVA para este médico y servicio.']);
            }

            return parent::save($options);
        }, 3);
    }

    protected $fillable = [
        'doctor_id',
        'service_id',
        'precio_primera_consulta',
        'precio_reconsulta',
        'dias_reconsulta',
        'estado',
    ];


    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
