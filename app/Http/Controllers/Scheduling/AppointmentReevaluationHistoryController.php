<?php
namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Services\Scheduling\AppointmentReevaluationService;
use Illuminate\Http\Request;

final class AppointmentReevaluationHistoryController extends Controller
{
    public function __invoke(Request $request,int $appointmentId,AppointmentReevaluationService $service)
    {
        $data=$request->validate(['page'=>'nullable|integer|min:1|max:100000',
            'direct_until'=>'nullable|integer|min:0','procedure_until'=>'nullable|integer|min:0']);
        $hasDirect=isset($data['direct_until']);$hasProcedure=isset($data['procedure_until']);
        abort_if($hasDirect!==$hasProcedure || (($data['page']??1)>1&&!$hasDirect),422,'Actualiza el historial para recuperar su referencia de lectura.');
        $cursor=$hasDirect?['direct_until'=>(int)$data['direct_until'],'procedure_until'=>(int)$data['procedure_until']]:null;
        return response()->json($service->history($appointmentId,$request->user(),(int)($data['page']??1),$cursor))
            ->header('Cache-Control','private, no-store');
    }
}
