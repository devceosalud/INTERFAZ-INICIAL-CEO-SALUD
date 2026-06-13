<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Specialty extends Model
{
    use HasFactory;


    protected $fillable = [
        'nombre',
        'estado',
    ];

    /**
     * Obtiene los doctores asociados a la especialidad.
     */
    public function doctors()
    {
        return $this->hasMany(Doctor::class);
    }


    /**
     * Obtiene los servicios asociados a la especialidad.
     */
    public function services()
    {
        return $this->hasMany(Service::class);
    }
}
