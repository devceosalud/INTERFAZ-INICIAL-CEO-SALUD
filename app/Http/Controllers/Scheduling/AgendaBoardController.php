<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Site;
use App\Models\Specialty;
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
            'legend' => AgendaLegend::ordered(),
            'views' => AgendaRange::views(),
            'today' => Carbon::today()->toDateString(),
        ]);
    }
}
