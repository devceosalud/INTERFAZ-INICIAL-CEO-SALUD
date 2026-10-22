<?php
namespace App\Http\Controllers\Scheduling;
use App\Http\Controllers\Controller;
use App\Services\Scheduling\AppointmentEconomicEdit;
use Illuminate\Http\Request;

final class AppointmentEconomicEditController extends Controller
{
    public function index(Request $request,int $appointmentId,AppointmentEconomicEdit $edits)
    {
        $data=$request->validate(['history_before'=>'nullable|integer|min:1']);
        return response()->json($edits->payload($appointmentId,$request->user(),$data['history_before']??null))->header('Cache-Control','private, no-store');
    }
    public function review(Request $request,int $appointmentId,AppointmentEconomicEdit $edits)
    {
        return response()->json($edits->review($appointmentId,$request->user(),$request->all()))->header('Cache-Control','private, no-store');
    }
    public function save(Request $request,int $appointmentId,AppointmentEconomicEdit $edits)
    {
        return response()->json($edits->save($appointmentId,$request->user(),$request->all())+['message'=>'Cambio económico guardado y auditado.'])->header('Cache-Control','private, no-store');
    }
}
