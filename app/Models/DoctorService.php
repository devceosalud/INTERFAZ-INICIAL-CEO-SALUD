<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorService extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'service_id',
        'precio_primera_consulta',
        'precio_reconsulta',
        'dias_reconsulta',
        'estado',
    ];


    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
