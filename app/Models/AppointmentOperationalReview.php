<?php
namespace App\Models;

class AppointmentOperationalReview extends \Illuminate\Database\Eloquent\Model
{
    protected $guarded = ['id'];
    protected $casts = ['ready_at' => 'datetime', 'version' => 'integer'];
}
