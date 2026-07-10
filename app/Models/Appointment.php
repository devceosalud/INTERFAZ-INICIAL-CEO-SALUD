<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero_cita',
        'user_id',
        'patient_id',
        'doctor_id',
        'service_id',
        'additional_rate_id',
        'fecha_cita',
        'hora_cita',
        'motivo_consulta',
        'precio_programado',
        'total_pagado',
        'saldo_pendiente',
        'es_exonerado',
        'autorizado_por',
        'estado_pagado',
        'estado_cita',
        'observaciones',
        'fecha_registro',
    ];

    /**
     * Obtiene el usuario (personal del sistema) que registró la cita.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
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
