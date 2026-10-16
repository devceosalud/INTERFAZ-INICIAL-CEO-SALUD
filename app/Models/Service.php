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
        'estado',
        'service_type', 'pricing_policy', 'institutional_price', 'price_valid_from', 'price_valid_until', 'pricing_notes', 'canonical_service_id',
    ];

    protected $casts = ['institutional_price' => 'decimal:2'];

    public function sharedSpecialties()
    {
        return $this->belongsToMany(Specialty::class,'service_specialties')->withPivot(['estado','authorized_by_user_id','authorization_reason'])->withTimestamps();
    }

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

    //TABLA PIVOT DE DOCTORES/SERVICIOS 
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
        )->withPivot('precio_primera_consulta', 'precio_reconsulta' ,'dias_reconsulta','estado');
    }
}
