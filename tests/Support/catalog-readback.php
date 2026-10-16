<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = \Tests\Support\IsolatedCatalogQa::boot(true);
use Illuminate\Support\Facades\DB;
$tables = ['appointments' => ['id', 'service_id', 'commercial_service_id', 'doctor_id', 'precio_programado', 'total_pagado', 'saldo_pendiente', 'estado_agenda', 'estado_cita', 'price_source'], 'doctor_services' => ['id', 'doctor_id', 'service_id', 'estado', 'precio_primera_consulta', 'precio_reconsulta', 'specialty_exception_approved_by_user_id'], 'catalog_price_events' => ['id', 'entity_type', 'entity_id', 'actor_user_id', 'action'], 'service_specialties' => ['id', 'service_id', 'specialty_id', 'estado', 'authorized_by_user_id']];
$r = [];
foreach ($tables as $table => $fields) {
    $r[$table] = DB::table($table)->select($fields)->orderBy('id')->get()->toArray();
}
$r['payments'] = ['count' => DB::table('payments')->count(), 'sum' => DB::table('payments')->sum('monto')];
$r['proofs'] = DB::table('appointment_documents')->whereNotNull('payment_id')->select(['appointment_id', 'payment_id', 'type'])->get()->toArray();
$r['vouchers'] = DB::table('vouchers')->count();
$r['users'] = DB::table('users')->count();
echo json_encode($r, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
