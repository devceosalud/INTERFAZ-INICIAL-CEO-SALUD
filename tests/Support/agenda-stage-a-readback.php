<?php
require dirname(__DIR__,2).'/vendor/autoload.php';$app=\Tests\Support\IsolatedAgendaStageAQa::boot();
\Illuminate\Support\Facades\DB::statement('SET TRANSACTION READ ONLY');\Illuminate\Support\Facades\DB::beginTransaction();
try{echo json_encode(['appointments'=>\Illuminate\Support\Facades\DB::table('appointments')->orderBy('id')->get(['id','has_diabetic_wound','is_postoperative','estado_agenda','estado_cita','hora_llegada','hora_salida','precio_programado','total_pagado']),
'payments'=>\Illuminate\Support\Facades\DB::table('payments')->orderBy('id')->get(['id','monto','metodo_pago']),
'operations'=>\Illuminate\Support\Facades\DB::table('appointment_operations')->count(),'events'=>\Illuminate\Support\Facades\DB::table('appointment_events')->count()],JSON_THROW_ON_ERROR);}
finally{\Illuminate\Support\Facades\DB::rollBack();}