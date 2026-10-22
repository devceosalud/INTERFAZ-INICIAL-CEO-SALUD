<?php
namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Services\Scheduling\ManualReevaluationService;
use Illuminate\Http\Request;

final class ManualReevaluationController extends Controller
{
    public function update(Request $r, int $appointmentId, ManualReevaluationService $service)
    {
        abort_unless($service->canUpdate($r->user()), 403);
        abort_if(array_diff(array_keys($r->all()), ['marked','expected_marked','expected_version','request_key','reason','_token']),
            422, 'Esta acción solo actualiza la marca operativa de reevaluación.');
        $data = $r->validate(['marked'=>'required|boolean', 'expected_marked'=>'required|boolean',
            'expected_version'=>'required|integer|min:0', 'request_key'=>'required|uuid',
            'reason'=>'nullable|string|max:500']);
        $a = $service->set($appointmentId, $r->user(), $data);
        return response()->json(['id'=>$a->id, 'manual_reevaluation'=>$service->state($a)])
            ->header('Cache-Control', 'private, no-store');
    }
}
