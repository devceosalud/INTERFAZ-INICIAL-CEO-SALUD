<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Existing request table; writes remain exclusively in the versioned procedure service. */
class AppointmentRequestedProcedure extends Model
{
    protected $guarded=['*'];
}
