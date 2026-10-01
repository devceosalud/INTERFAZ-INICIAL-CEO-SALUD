<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Channel;
use App\Models\InteractionMedium;
use App\Models\Site;
use App\Models\Specialty;
use App\Models\User;
use App\Support\Patients\PatientWriteAccess;
use App\Support\Scheduling\AgendaLegend;
use App\Support\Scheduling\AgendaRange;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

/**
 * The agenda board page. It ships filters and the legend only; every interval is fetched by
 * the feed for the window actually being looked at, so opening the page never loads a year.
 */
class AgendaBoardController extends Controller
{
    public function __invoke(): View
    {
        return view('scheduling.agenda.index', [
            'sites' => Site::activo()->orderBy('nombre')->get(['id', 'nombre']),
            'specialties' => Specialty::where('estado', 'ACTIVO')->orderBy('nombre')->get(['id', 'nombre']),
            'doctors' => Doctor::where('estado', 'ACTIVO')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'specialty_id']),
            'channels' => Channel::where('estado', 'ACTIVO')->orderBy('nombre')->get(['id', 'nombre']),
            'interactionMedia' => InteractionMedium::where('estado', 'ACTIVO')->orderBy('nombre')->get(['id', 'nombre']),
            'commercialUsers' => User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', 'COMERCIAL'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'canWritePatients' => PatientWriteAccess::allows(auth()->user()),
            'legend' => AgendaLegend::ordered(),
            'views' => AgendaRange::views(),
            'today' => Carbon::today()->toDateString(),
        ]);
    }
}
