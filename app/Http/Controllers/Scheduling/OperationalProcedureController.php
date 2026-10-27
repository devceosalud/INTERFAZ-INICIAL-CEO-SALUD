<?php
namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Services\Scheduling\OperationalProcedureService;
use App\Services\Scheduling\DoctorProcedureSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class OperationalProcedureController extends Controller
{
    public function personalSearch(Request $r,DoctorProcedureSuggestionService $suggestions)
    {
        \App\Support\Scheduling\ClinicalAccess::actor($r->user(),\App\Support\Scheduling\ClinicalCapability::PROCEDURES);
        $data=$r->validate(['query'=>'required|string|min:2|max:120']);
        return response()->json(['items'=>$suggestions->list($r->user(),$data['query'])])->header('Cache-Control','private, no-store');
    }
    public function queue(Request $r, OperationalProcedureService $service)
    {
        $groups=['active'=>[], 'pre_pending'=>[], 'pending'=>[], 'other_procedures'=>[], 'history'=>[]];
        foreach($service->queue($r->user()) as $row){
            $group=$row['review']['status']==='READY' ? 'active'
                : ($row['review']['status']==='PRE_PENDING' ? 'pre_pending'
                    : ($row['review']['status']==='PENDING' ? 'pending'
                        : ($row['review']['status']==='NOT_READY' && $row['requested_count']>0 ? 'other_procedures' : 'history')));
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
        // Nested wildcard "distinct" compares values across different procedures in Laravel.
        // Enforce uniqueness within each procedure's own lateralities/projections instead.
        $distinctWithinProcedure = static function ($attribute, $value, $fail): void {
            if (is_array($value) && count($value) !== count(array_unique($value, SORT_REGULAR))) {
                $fail('No repitas valores dentro de las características del mismo procedimiento.');
            }
        };
        $rules = ['request_key' => 'required|uuid', 'expected_version' => 'required|integer|min:0'];
        $rules += match ($action) {
            'selection' => ['clinical_order_revision'=>'sometimes|string|size:64', 'expected_patient_id'=>'required_with:clinical_order,procedure_details|integer|min:1',
                'clinical_order'=>'sometimes|array:diagnosis,observations', 'clinical_order.diagnosis'=>'nullable|string|max:500', 'clinical_order.observations'=>'nullable|string|max:600',
                'procedure_details'=>'sometimes|array|max:130', 'procedure_details.*'=>'array:sides,with_load,projections,finger', 'procedure_details.*.sides'=>['sometimes','array','max:3',$distinctWithinProcedure], 'procedure_details.*.sides.*'=>['string',Rule::in(['D','I','B'])],
                'procedure_details.*.with_load'=>'sometimes|boolean', 'procedure_details.*.projections'=>['sometimes','array','max:3',$distinctWithinProcedure], 'procedure_details.*.projections.*'=>['string',Rule::in(['AP','LAT','OBLIQUE'])], 'procedure_details.*.finger'=>'nullable|integer|min:1|max:5',
                'procedures' => 'present|array|max:100', 'procedures.*' => ['required', 'string', 'distinct', Rule::in(array_keys(\App\Support\Scheduling\ProcedureCatalog::labels()))],
                'custom_procedures'=>'sometimes|array|max:30', 'custom_procedures.*'=>'array:id,label,family,reusable_id', 'custom_procedures.*.reusable_id'=>'nullable|uuid',
                'custom_procedures.*.id'=>'required|uuid|distinct', 'custom_procedures.*.label'=>'required|string|max:500', 'custom_procedures.*.family'=>['sometimes','string',Rule::in(array_keys(\App\Support\Scheduling\ProcedureCatalog::FAMILIES))]],
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
