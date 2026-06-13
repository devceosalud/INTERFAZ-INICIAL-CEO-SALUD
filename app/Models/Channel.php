<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'estado',
    ];

    /**
     * Obtiene las citas que ingresaron por este canal.
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
