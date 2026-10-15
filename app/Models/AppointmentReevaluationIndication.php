<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AppointmentReevaluationIndication extends Model {
    protected $guarded=['id'];
    protected $casts=['indicated_on'=>'date:Y-m-d','attended_on'=>'date:Y-m-d'];
}
