<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentDocument extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    protected $guarded = ['id'];
    public function appointment() { return $this->belongsTo(Appointment::class); }
}
