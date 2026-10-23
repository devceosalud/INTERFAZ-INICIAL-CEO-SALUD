<?php

namespace App\Http\Controllers\receptionist\appointment;

use App\Http\Controllers\Controller;
use App\Models\AdditionalRate;
use App\Models\Appointment;
use App\Models\Channel;
use App\Models\InteractionMedium;
use App\Models\Specialty;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    //
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $actor=auth()->user();
        abort_unless(config('scheduling.enabled') && $actor->is_active
            && $actor->can(\App\Support\Scheduling\SchedulingCapability::MVP_ACCESS)
            && $actor->can(\App\Support\Scheduling\SchedulingCapability::VIEW),403,
            'Agenda operativa no está disponible para este usuario.');
        return redirect()->route('scheduling.mvp.agenda');
    }
}
