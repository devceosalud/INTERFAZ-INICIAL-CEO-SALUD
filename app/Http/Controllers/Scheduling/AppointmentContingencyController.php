<?php
namespace App\Http\Controllers\Scheduling;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentContingencyController extends Controller
{
    public function index(Request $r)
    {
        $visible = \App\Models\Appointment::visibleToAgendaUser($r->user()->id)->select('appointments.id');
        $rows = DB::table('appointment_contingencies as c')->join('appointments as a', 'a.id', '=', 'c.appointment_id')
            ->join('patients as p', 'p.id', '=', 'a.patient_id')->join('appointment_events as e', 'e.id', '=', 'c.event_id')
            ->where('c.owner_user_id', $r->user()->id)->whereIn('a.id', $visible)->where('c.status', 'ABIERTA')
            ->orderByDesc('c.id')->limit(100)->get(['c.id', 'c.appointment_id', 'c.status', 'c.read_at',
                'a.fecha_cita', 'a.hora_cita', 'e.motivo', 'p.nombre', 'p.apellido_paterno', 'p.apellido_materno']);
        return response()->json(['contingencies' => $rows->map(fn ($row) => ['id' => $row->id, 'appointment_id' => $row->appointment_id,
            'status' => $row->status, 'read_at' => $row->read_at, 'fecha' => substr($row->fecha_cita, 0, 10), 'hora' => substr($row->hora_cita, 0, 5),
            'patient' => trim($row->nombre.' '.$row->apellido_paterno.' '.$row->apellido_materno), 'cause' => $row->motivo,
            'pending_action' => 'Contactar al paciente y revisar una nueva disponibilidad'])]);
    }
    public function update(Request $r, int $id)
    {
        $data = $r->validate(['resolution' => 'nullable|string|max:2000']);
        return DB::transaction(function () use ($r, $id, $data) {
            $row = DB::table('appointment_contingencies')->where('owner_user_id', $r->user()->id)->where('id', $id)->lockForUpdate()->first();
            abort_unless($row && \App\Models\Appointment::visibleToAgendaUser($r->user()->id)->whereKey($row->appointment_id)->exists(), 404);
            $changes = ['read_at' => now(), 'updated_at' => now()];
            if (!empty($data['resolution'])) { $changes += ['status' => 'RESUELTA', 'resolved_at' => now(),
                'resolved_by_user_id' => $r->user()->id, 'resolution' => $data['resolution']]; }
            DB::table('appointment_contingencies')->where('id', $id)->update($changes);
            return response()->json(['message' => 'Seguimiento actualizado. La cita no se canceló.']);
        });
    }
}
