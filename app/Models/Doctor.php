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
     * Obtiene la agenda de citas programadas para el médico.
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
