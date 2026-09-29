<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Services\Scheduling\DoctorAvailabilityService;
use App\Support\Scheduling\AvailabilityQuery;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only contract over the availability engine, so the future calendar consumes the same
 * rules the backend applies instead of recomputing them in JavaScript.
 *
 * The response carries operating intervals and their state only. No patient data.
 */
class DoctorAvailabilityController extends Controller
{
    public function __invoke(Request $request, DoctorAvailabilityService $availability): JsonResponse
    {
        $data = $request->validate([
            'doctor_id' => 'required|integer|exists:doctors,id',
            'fecha' => 'required|date',
            'site_id' => 'nullable|integer|exists:sites,id',
            'duracion' => 'nullable|integer|min:5|max:480',
        ]);

        $result = $availability->forDay(new AvailabilityQuery(
            (int) $data['doctor_id'],
            Carbon::parse($data['fecha']),
            isset($data['site_id']) ? (int) $data['site_id'] : null,
            isset($data['duracion']) ? (int) $data['duracion'] : null
        ));

        return response()->json($result->toArray());
    }
}
