<?php
namespace App\Services\Scheduling;

use App\Models\Appointment;
use Illuminate\Http\Request;

/** Retained route adapter: every role creates exclusively through Agenda operativa. */
class LegacyAppointmentRegistration
{
    public function register(Request $request): Appointment
    {
        abort(403, 'Registra nuevas citas exclusivamente desde Agenda operativa.');
    }
}
