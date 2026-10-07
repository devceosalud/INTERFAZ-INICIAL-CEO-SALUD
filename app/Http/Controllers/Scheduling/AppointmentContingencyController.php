<?php
namespace App\Http\Controllers\Scheduling;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentContingencyController extends Controller
{
    public function index(Request $r)
    {
        return response()->json(['contingencies' => DB::table('appointment_contingencies')->where('owner_user_id', $r->user()->id)
            ->where('status', 'ABIERTA')->orderByDesc('id')->limit(100)->get(['id', 'appointment_id', 'status', 'notified_at', 'read_at'])]);
    }
    public function update(Request $r, int $id)
    {
        $data = $r->validate(['resolution' => 'nullable|string|max:2000']);
        return DB::transaction(function () use ($r, $id, $data) {
            $row = DB::table('appointment_contingencies')->where('owner_user_id', $r->user()->id)->where('id', $id)->lockForUpdate()->first();
            abort_unless($row, 404);
            $changes = ['read_at' => now(), 'updated_at' => now()];
            if (!empty($data['resolution'])) { $changes += ['status' => 'RESUELTA', 'resolved_at' => now(),
                'resolved_by_user_id' => $r->user()->id, 'resolution' => $data['resolution']]; }
            DB::table('appointment_contingencies')->where('id', $id)->update($changes);
            return response()->json(['message' => 'Seguimiento actualizado. La cita no se canceló.']);
        });
    }
}
