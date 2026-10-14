<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentDocument extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    protected $guarded = ['id'];
    protected $casts = ['receipt_review'=>'array','reviewed_at'=>'datetime'];
    protected $hidden = ['private_path','payment_proof_hash'];
    public function payment() { return $this->belongsTo(Payment::class); }
    public function appointment() { return $this->belongsTo(Appointment::class); }
}
