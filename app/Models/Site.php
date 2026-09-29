<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo',
        'nombre',
        'estado',
    ];

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('estado', 'ACTIVO');
    }

    /**
     * Citas registradas en la sede.
     */
    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Horarios médicos atendidos en la sede.
     */
    public function doctorSchedules()
    {
        return $this->hasMany(DoctorSchedule::class);
    }
}
