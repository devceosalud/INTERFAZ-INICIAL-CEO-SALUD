<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Services\Scheduling\AdmissionTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdmissionTaskController extends Controller
{
    public function store(Request $request, int $appointmentId, AdmissionTaskService $tasks): JsonResponse
    {
        abort_if(array_diff(array_keys($request->all()), ['text', 'request_key', '_token']),
            422, 'Esta acción solo crea una tarea de seguimiento.');
        $data = $request->validate([
            'text' => 'required|string|max:16000',
            'request_key' => 'required|uuid',
        ], ['text.required' => 'Escribe una observación antes de guardar.',
            'text.max' => 'La observación supera el máximo técnico de 16000 caracteres.']);
        $tasks->create($appointmentId, $request->user(), $data['text'], $data['request_key']);
        return response()->json(['message' => 'Observación registrada para esta cita.'], 201)
            ->header('Cache-Control', 'private, no-store');
    }

    public function completion(
        Request $request,
        int $appointmentId,
        int $taskId,
        AdmissionTaskService $tasks
    ): JsonResponse {
        abort_if(array_diff(array_keys($request->all()), ['expected_completed', 'completed', '_token']),
            422, 'Solo puedes cambiar el estado de realización.');
        $data = $request->validate([
            'expected_completed' => 'required|boolean',
            'completed' => 'required|boolean',
        ]);
        $tasks->setCompleted(
            $appointmentId,
            $taskId,
            $request->user(),
            (bool) $data['expected_completed'],
            (bool) $data['completed']
        );
        return response()->json(['message' => (bool) $data['completed']
            ? 'Tarea marcada como realizada.' : 'Tarea marcada nuevamente como pendiente.'])
            ->header('Cache-Control', 'private, no-store');
    }
}
