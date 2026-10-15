<?php
require dirname(__DIR__, 2).'/vendor/autoload.php';
\Tests\Support\IsolatedAgendaQa::boot();
$history = json_decode(file_get_contents(storage_path('app/qa-final/browser-historical-before.json')), true);
$historic = \App\Models\Appointment::findOrFail($history['id']);
if ($history !== $historic->getAttributes()) { throw new RuntimeException('Historical QA fixture changed.'); }
$rows = \App\Models\Appointment::where('id', '<>', $history['id'])->orderBy('id')
    ->get(['id', 'fecha_cita', 'hora_cita', 'precio_programado', 'total_pagado', 'saldo_pendiente', 'estado_agenda', 'estado_cita']);
$result = ['historical_unchanged' => true, 'appointments' => $rows->toArray(),
    'payments' => \App\Models\Payment::count(), 'paid_sum' => \App\Models\Payment::sum('monto'),
    'occupying_intervals' => \App\Models\Appointment::occupyingInterval()->count()];
file_put_contents(storage_path('app/qa-final/urgent-browser-check.json'), json_encode($result, JSON_PRETTY_PRINT));
echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
