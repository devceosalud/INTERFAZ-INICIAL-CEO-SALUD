<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Services\Scheduling\AgendaBoardPresenter;
use App\Support\Scheduling\AgendaRange;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Availability and occupancy of the professionals being compared, for the window being looked at.
 *
 * Occupancy identity is operational and authenticated: patient display name and service only.
 * The generic availability endpoint remains a separate contract and still carries no PII.
 */
class AgendaFeedController extends Controller
{
    /**
     * Ceiling for an explicit selection. Comparing more professionals than this stops being
     * readable on one screen long before it stops being fast.
     */
    public const MAX_SELECTED = 12;

    /**
     * How many professionals are shown when the user has not chosen any. A default board must
     * answer "who has room today" without loading every professional in the clinic.
     */
    public const DEFAULT_SHOWN = 8;

    public function __invoke(Request $request, AgendaBoardPresenter $presenter): JsonResponse
    {
        $data = $request->validate([
            'vista' => 'required|in:'.implode(',', AgendaRange::views()),
            'fecha' => 'required|date',
            'doctor_id' => 'nullable|array|max:'.self::MAX_SELECTED,
            'doctor_id.*' => 'integer|exists:doctors,id',
            'specialty_id' => 'nullable|integer|exists:specialties,id',
            'site_id' => 'nullable|integer|exists:sites,id',
        ]);

        $range = new AgendaRange($data['vista'], Carbon::parse($data['fecha']));
        $selectable = $this->selectableDoctors($data);
        $shown = $selectable->take(
            isset($data['doctor_id']) ? self::MAX_SELECTED : self::DEFAULT_SHOWN
        );

        $payload = $presenter->build(
            $range,
            $shown,
            isset($data['site_id']) ? (int) $data['site_id'] : null
        );

        return response()->json($payload + [
            'profesionales_coincidentes' => $selectable->count(),
            'profesionales_mostrados' => $shown->count(),
            'truncado' => $selectable->count() > $shown->count(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, Doctor>
     */
    protected function selectableDoctors(array $data): Collection
    {
        return Doctor::with('specialty:id,nombre')
            ->where('estado', 'ACTIVO')
            ->when(isset($data['doctor_id']), fn ($query) => $query->whereIn('id', $data['doctor_id']))
            ->when(isset($data['specialty_id']), fn ($query) => $query->where('specialty_id', $data['specialty_id']))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'specialty_id']);
    }
}
