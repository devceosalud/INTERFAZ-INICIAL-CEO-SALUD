<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdditionalRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'tipo_tarifa',
        'tarifa',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    /**
     * Obtiene las citas médicas a las que se les ha aplicado esta tarifa adicional.
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'additional_rate_id');
    }
}
