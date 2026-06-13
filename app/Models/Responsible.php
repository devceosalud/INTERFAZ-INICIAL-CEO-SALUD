<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Responsible extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'tipo_identificacion',
        'numero_identidad',
        'nombres',
        'telefono',
        'parentezco',
        'estado',
    ];


    /**
     * Obtiene el paciente asignado a este responsable.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
