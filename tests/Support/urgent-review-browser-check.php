<?php
require dirname(__DIR__, 2).'/vendor/autoload.php';
\Tests\Support\IsolatedAgendaQa::boot();
$historicIds = [];
foreach (['browser-historical-before.json', 'review-attended-before.json'] as $file) {
    $before = json_decode(file_get_contents(storage_path('app/qa-final/'.$file)), true);
    $a = \App\Models\Appointment::findOrFail($before['id']);
    if ($before !== $a->getAttributes()) { throw new RuntimeException('Historical QA fixture changed.'); }
    $historicIds[] = $a->id;
}
$rows = \App\Models\Appointment::whereNotIn('id', $historicIds)->get(['id', 'precio_programado',
    'total_pagado', 'saldo_pendiente', 'estado_agenda', 'estado_cita']);
$count = \App\Models\Payment::count(); $sum = \App\Models\Payment::sum('monto');
if ($count !== 1 || (float) $sum !== 50.0 || $rows->count() !== 1
    || $rows->first()->estado_agenda !== 'CONFIRMADA' || (float) $rows->first()->precio_programado !== 100.0) {
    throw new RuntimeException('Browser payment/confirmation results differ from the QA scenario.');
}
echo json_encode(['historical_unchanged' => true, 'historic_fixture_ids' => $historicIds,
    'appointments' => $rows->toArray(), 'payments' => $count, 'paid_sum' => $sum], JSON_PRETTY_PRINT).PHP_EOL;
