<?php
// Reuse the visual release's untouched guarded SQLite browser runtime and synthetic cohort.
require __DIR__.'/reschedule-visual-browser-seed.php';
$actor=$data['actor'];$actor->syncRoles([\Spatie\Permission\Models\Role::findOrCreate('COMERCIAL','web')]);
$actor->forceFill(['name'=>'COMERCIAL FICTICIO MVP'])->save();
$data['catalog']['rate']->update(['tipo_tarifa'=>'MONTO_FIJO']);
$payment=\App\Models\Payment::firstOrFail();$payment->update(['monto'=>50,'metodo_pago'=>'YAPE','numero_operacion'=>'FAKE-MVP-ORIGINAL']);
$data['a']->update(['total_pagado'=>50,'saldo_pendiente'=>70]);
$registered=\App\Models\AppointmentEvent::where('appointment_id',$data['a']->id)->where('event_type','PAGO_REGISTRADO')->firstOrFail();
$meta=$registered->metadata;$meta['amount']='50.00';$registered->update(['metadata'=>$meta]);
$helper=new class {use \Tests\Concerns\BuildsAgendaLifecycleData;public function admin(){return $this->agendaReader('ADMINISTRADOR');}};
$admin=$helper->admin();$admin->forceFill(['name'=>'ADMIN FICTICIO MVP','email'=>'mvp-admin@example.invalid','password'=>\Illuminate\Support\Facades\Hash::make($password)])->save();
$admin->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate(\App\Support\Scheduling\SchedulingCapability::RESCHEDULE,'web'));
$rate=\App\Models\AdditionalRate::create(['nombre'=>'RECARGO FIJO FICTICIO S20','tipo_tarifa'=>'MONTO_FIJO','tarifa'=>20,'estado'=>'ACTIVO']);
$f=json_decode(file_get_contents($dir.'/browser-fixture.json'),true);
$f+=['admin_email'=>$admin->email,'admin_role_id'=>\Spatie\Permission\Models\Role::findByName('ADMINISTRADOR','web')->id,'rate_id'=>$rate->id,'payment_id'=>$payment->id];
file_put_contents($dir.'/browser-fixture.json',json_encode($f,JSON_PRETTY_PRINT));
echo "Direct economic synthetic cohort ready; permissions remain unassigned until browser Role editor test.\n";
