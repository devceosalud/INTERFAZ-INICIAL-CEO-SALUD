<?php
namespace App\Models;

class AppointmentOperationalReview extends \Illuminate\Database\Eloquent\Model
{
    protected $guarded = ['id'];
    protected $casts = ['ready_at' => 'datetime', 'version' => 'integer', 'requested_count' => 'integer', 'completed_count' => 'integer'];

    public function decidedBy() { return $this->belongsTo(User::class, 'decided_by_user_id'); }
}
