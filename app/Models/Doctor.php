<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable = [
        'specialty_id',
        'nombre',
        'cmp',
        'rne',
        'estado',
    ];


    /**
     * Obtiene la especialidad del doctor.
     */
    public function specialty()
    {
        return $this->belongsTo(Specialty::class);
    }


    /**
     * Obtiene la agenda de citas programadas para el doctor.
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Obtenermos el horario del doctro
     */
    public function schedules()
    {
        return $this->hasMany(DoctorSchedule::class);
    }

    public function doctorServices()
    {
        return $this->hasMany(DoctorService::class);
    }


    //RELACION PIVOT
    public function services()
    {
        return $this->belongsToMany(
            Service::class,
            'doctor_services',
        )->withPivot('precio_primera_consulta', 'precio_reconsulta', 'dias_reconsulta', 'estado');
    }

    public function voucherItems()
    {
        return $this->hasMany(VoucherItem::class);
    }
}
