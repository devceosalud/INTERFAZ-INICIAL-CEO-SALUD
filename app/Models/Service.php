<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'specialty_id',
        'nombre',
        'precio_primera_consulta',
        'precio_reconsulta',
        'dias_reconsulta',
        'estado',
    ];

    /**
     * Obtiene la specialty a la que pertenece el servicio.
     */
    public function specialty()
    {
        return $this->belongsTo(Specialty::class);
    }

    /**
     * Obtiene las citas que han solicitado este servicio.
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function doctorServices()
    {
        return $this->hasMany(DoctorService::class);
    }

    //RELACION PIVOT
    public function doctors()
    {
        return $this->belongsToMany(
            Doctor::class,
            'doctor_services'
        )->withPivot('precio', 'estado');
    }
}
