<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AppointmentEvent extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['metadata' => 'array', 'occurred_at' => 'datetime'];
    public function appointment() { return $this->belongsTo(Appointment::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_user_id'); }
}
