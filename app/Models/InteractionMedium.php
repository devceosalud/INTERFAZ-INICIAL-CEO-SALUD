<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InteractionMedium extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'estado',
    ];


    /**
     * Obtiene las citas asociadas a este medio de interacción.
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'interaction_medium_id');
    }
}
