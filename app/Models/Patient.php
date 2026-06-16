<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'historia_clinica',
        'historia_clinica_nueva',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'genero',
        'tipo_identificacion',
        'numero_identidad',
        'telefono',
        'channel_id',
        'interaction_medium_id',
        'fecha_registro',
        'fecha_nacimiento',
        'ocupacion',
        'grado_instruccion',
        'direccion',
        'email',
        'familiar_contacto',
        'estado_civil',
        'estado',
    ];



    /**
     * Obtiene el usuario del sistema asociado al paciente.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtiene los responsables asociados al paciente.
     */
    public function responsibles()
    {
        return $this->hasMany(Responsible::class);
    }

    /**
     * Obtiene el historial de citas del paciente.
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Obtiene el canal de captación o comunicación asociados al paciente.
     */
    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Obtiene el medio de interacción asociado al paciente.
     */
    public function interactionMedium()
    {
        return $this->belongsTo(InteractionMedium::class, 'interaction_medium_id');
    }
}
