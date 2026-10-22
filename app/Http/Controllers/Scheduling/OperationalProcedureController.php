<?php
namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Services\Scheduling\OperationalProcedureService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class OperationalProcedureController extends Controller
{
    public static function selectionRules(): array
    {
        return ['procedures' => 'present|array|max:7', 'procedures.*' => ['required', 'string', 'distinct', Rule::in(array_keys(OperationalProcedureService::CATALOG))],
                'custom_procedures'=>'sometimes|array|max:30', 'custom_procedures.*'=>'array:id,label',
                'custom_procedures.*.id'=>'required|uuid|distinct', 'custom_procedures.*.label'=>'required|string|max:500'];
    }

    public function queue(Request $r, OperationalProcedureService $service)
    {
        $groups=['active'=>[], 'pending'=>[], 'history'=>[]];
        foreach($service->queue($r->user()) as $row){
            $group=$row['review']['status']==='READY' ? 'active'
                : ($row['review']['status']==='NOT_READY' && $row['requested_count']>0 ? 'pending' : 'history');
            $groups[$group][]=$row;
        }
        return response()->json(['rows'=>$groups['active'],'groups'=>$groups])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $r, int $appointmentId, string $action, OperationalProcedureService $service)
    {
        abort_unless(match ($action) {
            'selection' => $service->canSelect($r->user()), 'completion' => $service->canComplete($r->user()),
            'send', 'withdraw' => $service->canSend($r->user()), default => false,
        }, 403);
        $rules = ['request_key' => 'required|uuid', 'expected_version' => 'required|integer|min:0'];
        $rules += match ($action) {
            'selection' => self::selectionRules(),
            'completion' => ['procedure' => 'required|string|max:40', 'completed' => 'required|boolean'],
            default => ['confirmed' => 'required|accepted', 'reason' => 'nullable|string|max:500'],
        };
        abort_if(array_diff(array_keys($r->all()), array_merge(array_keys($rules), ['_token'])), 422, 'Campos no permitidos para esta acción.');
        $data = $r->validate($rules);
        $data['expected_version'] = (int) $data['expected_version'];
        if (isset($data['completed'])) $data['completed'] = (bool) $data['completed'];
        if (isset($data['confirmed'])) $data['confirmed'] = true;
        $service->mutate($appointmentId, $r->user(), $action, $data);
        return response()->json(['message' => 'Procedimientos y reevaluación actualizados.'])->header('Cache-Control', 'private, no-store');
    }
}
