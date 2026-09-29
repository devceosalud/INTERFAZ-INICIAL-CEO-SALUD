<?php

namespace App\Http\Controllers\Api\doctorSchedule;

use App\Http\Controllers\Controller;
use App\Models\DoctorSchedule;
use App\Services\Scheduling\DoctorAvailabilityService;
use App\Support\Scheduling\AvailabilityQuery;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DoctorScheduleController extends Controller
{
    /**
     * Free hours for a professional on a date, already resolved by the availability engine.
     *
     * Until MVP-2B this returned the raw blocks and the raw occupied appointments and let three
     * copies of JavaScript work out the slots, which is where the real defects lived. The
     * response now carries only resolved slots: no raw appointment rows and no patient data.
     */
    public function availableHours(Request $request, DoctorAvailabilityService $availability)
    {
        $data = $request->validate([
            'doctor_id' => 'required|integer',
            'fecha_cita' => 'required|date',
            'cita_doble' => 'nullable|boolean',
        ]);

        $slots = $availability->forDay(new AvailabilityQuery(
            (int) $data['doctor_id'],
            Carbon::parse($data['fecha_cita']),
            null,
            null,
            $request->boolean('cita_doble') ? 2 : 1
        ))->availableSlots();

        return response()->json([
            'slots' => $slots->map->toArray()->values(),
        ]);
    }

    public function search(Request $request)
    {
        $doctor_schedule = DoctorSchedule::find($request->id);

        if (!$doctor_schedule) {
            return response()->json(['message' => 'no encontrado'], 404);
        } else {
            return response()->json([
                'message' => 'encontrado',
                'doctor_schedule' => $doctor_schedule
            ], 200);
        }
    }
}
