<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorSchedule extends Model
{
    use HasFactory;

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
