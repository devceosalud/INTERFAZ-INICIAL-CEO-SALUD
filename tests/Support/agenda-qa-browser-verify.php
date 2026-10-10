<?php
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = \Tests\Support\IsolatedAgendaQa::boot();
use Illuminate\Support\Facades\DB;
use App\Models\Appointment;
use App\Models\Payment;
$before = json_decode(file_get_contents(storage_path('app/qa-final/browser-historical-before.json')), true, 512, JSON_THROW_ON_ERROR);
$old = Appointment::findOrFail($before['id']);
$checks = [
    'historical_row_unchanged' => $old->getAttributes() === $before,
    'appointments_four_only' => Appointment::count() === 4,
    'double_click_one_reservation' => Appointment::where('hora_cita', '09:00:00')->count() === 1,
    'unpaid_reservations_pending' => Appointment::where('estado_agenda', 'PENDIENTE_CONFIRMACION')->where('total_pagado', 0)->count() === 2,
    'one_funded_confirmation' => Appointment::where('estado_agenda', 'CONFIRMADA')->where('total_pagado', 50)->count() === 1,
    'only_two_real_fictitious_payments' => Payment::count() === 2 && (float) Payment::sum('monto') === 50.0,
    'only_one_ticket' => DB::table('vouchers')->count() === 1,
    'operations_six_only' => DB::table('appointment_operations')->count() === 6,
    'historical_and_confirmation_occupy' => Appointment::occupyingInterval()->count() === 2,
    'patients_preserved' => DB::table('patients')->count() === 2 && DB::table('patients')->where('numero_identidad', '70000001')
        ->where('nombre', 'PACIENTE QA')->where('telefono', '+51999000001')->where('historia_clinica', 'QA-LOCAL-1')->count() === 1,
    'users_and_roles_preserved' => DB::table('users')->count() === 4 && DB::table('model_has_roles')->count() === 4
        && DB::table('model_has_permissions')->count() === 12,
    'schedule_unchanged' => DB::table('doctor_schedules')->count() === 1
        && DB::table('doctor_schedules')->where('hora_inicio', '08:00:00')->where('hora_fin', '12:00:00')->count() === 1,
];
$result = ['checks' => $checks, 'appointments' => Appointment::orderBy('id')->get(['id', 'hora_cita', 'estado_agenda', 'tipo_agendamiento', 'total_pagado'])->toArray(),
    'payments' => Payment::orderBy('id')->pluck('monto')->all()];
file_put_contents(storage_path('app/qa-final/browser-db-results.json'), json_encode($result, JSON_PRETTY_PRINT));
echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
exit(in_array(false, $checks, true) ? 1 : 0);
