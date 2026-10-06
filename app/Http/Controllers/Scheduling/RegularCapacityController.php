<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Services\Scheduling\RegularCapacityService;
use App\Support\Scheduling\AgendaQuery;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RegularCapacityController extends Controller
{
    public function __invoke(Request $request, RegularCapacityService $capacity)
    {
        $data = $request->validate(['doctor_id' => 'required|integer|exists:doctors,id',
            'from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from',
            'site_id' => 'nullable|integer|exists:sites,id']);
        $from = Carbon::parse($data['from']); $to = Carbon::parse($data['to']);
        if ($from->diffInDays($to) > 41) { throw ValidationException::withMessages(['to' => 'El rango máximo es de 42 días.']); }
        return response()->json(['dias' => $capacity->forRange(new AgendaQuery([(int) $data['doctor_id']], $from, $to,
            isset($data['site_id']) ? (int) $data['site_id'] : null))]);
    }
}
