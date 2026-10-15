const {launch}=require('./browser-harness.cjs'),{helpers}=require('./browser-helpers.cjs');
(async()=>{const qa=await launch('legacy');try{const {page}=await qa.context('nicole'),{page:admin}=await qa.context('admin'),h=helpers(qa);
 const d=new Date(qa.fixture.date+'T12:00:00Z');d.setUTCDate(d.getUTCDate()+2);const date=d.toISOString().slice(0,10);let appointmentId;
 const occupied=qa.readback().appointments.filter(a=>a.fecha_cita===date).map(a=>a.hora_cita.slice(0,5));const start=[13,15,10,11].find(hour=>!occupied.includes(String(hour)+':00'));qa.assert.ok(start);const startTime=String(start)+':00',targetTime=String(start+1)+':00';
 await qa.step('ADMIN configura un horario futuro ficticio por interfaz para el flujo heredado',admin,async()=>{
  if(qa.readback().schedule_versions.some(s=>s.fecha_cita===date&&s.estado==='ACTIVO'))return;
  await admin.goto(qa.base+'/admissionist/doctor-schedule');await admin.locator('.fc-daygrid-day[data-date="'+date+'"]').click({position:{x:25,y:65}});await admin.getByRole('button',{name:'Configurar selección',exact:true}).click();
  qa.assert.equal(await admin.locator('#schedule-date').inputValue(),date);
  for(const[id,value]of Object.entries({'schedule-doctor':'2','schedule-site':String(qa.fixture.site_id),'schedule-start-hour':'08','schedule-start-minute':'00','schedule-end-hour':'18','schedule-end-minute':'00','schedule-duration':'30'}))await admin.locator('#'+id).selectOption(value,{force:true});
  const response=admin.waitForResponse(r=>r.url().endsWith('/doctor-schedule/store')&&r.request().method()==='POST');await admin.locator('#schedule-save').click();await admin.locator('.swal2-confirm').click();const r=await response;qa.assert.equal(r.status(),200);qa.assert.equal((await r.json()).code,1);await admin.locator('#schedule-editor').waitFor({state:'hidden'});
 });
 await page.goto(qa.base+'/admissionist/appointment');
 await qa.step('Calendario heredado utiliza recursos locales y carga sin dependencias externas',page,async()=>{qa.assert.equal(await page.evaluate(()=>typeof FullCalendar),'object');await page.locator('#calendar .fc-view-harness').waitFor();});
 await qa.step('Formulario heredado reserva doble duración con precio único calculado y sin dinero',page,async()=>{
  const previous=qa.readback();await page.locator('#calendar .fc-daygrid-day[data-date="'+date+'"]').click({position:{x:25,y:65}});const form=page.locator('#formCreateAppointment');await form.waitFor();
  await form.locator('#documento_paciente').fill('72000001');await page.waitForFunction(()=>Boolean(document.querySelector('#formCreateAppointment #patient_id').value));
  await form.locator('#specialty_id').selectOption('2',{force:true});await form.locator('#doctor_id option[value="2"]').waitFor({state:'attached'});await form.locator('#doctor_id').selectOption('2',{force:true});
  await form.locator('#service_id option[value="2"]').waitFor({state:'attached'});await form.locator('#service_id').selectOption('2',{force:true});await form.locator('#cita_doble').check();
  await page.waitForFunction(()=>Number(document.querySelector('#formCreateAppointment #precio_programado_hidden').value)===100);
  await form.locator('#hora_cita').fill(startTime);await form.locator('[name=mode]').selectOption('RESERVE',{force:true});await form.locator('#total_pagado').fill('0');
  const response=page.waitForResponse(r=>r.url().endsWith('/admissionist/appointment/store')&&r.request().method()==='POST');await form.locator('input[type=submit]').click();const r=await response,body=await r.json();qa.assert.equal(r.status(),200,JSON.stringify(body));qa.assert.equal(body.code,1);appointmentId=body.appointment_id;
  await page.waitForURL('**/admissionist/appointment');const after=qa.readback(),a=after.appointments.find(x=>x.id===appointmentId);qa.assert.ok(a);qa.assert.equal(a.estado_agenda,'PENDIENTE_CONFIRMACION');qa.assert.equal(a.duration_slots,2);qa.assert.equal(a.duracion_cita,60);qa.assert.equal(Number(a.precio_programado),100);qa.assert.deepEqual(after.payments,previous.payments);h.save('legacy-ids',{appointment:appointmentId,date});
 });
 await qa.step('Reprogramación heredada conserva indicación de duración y exige destino válido sin alterar pagos',page,async()=>{
  await page.locator('#filtro-calendar_specialty_id').selectOption('2',{force:true});await page.locator('#filtro-calendar_doctor_id option[value="2"]').waitFor({state:'attached'});await page.locator('#filtro-calendar_doctor_id').selectOption('2',{force:true});
  const event=page.locator('#calendar .fc-daygrid-day[data-date="'+date+'"] .fc-event').filter({hasText:'PACIENTE JORNADA QA'}).filter({hasText:String(start)+':00'});await event.first().click();const form=page.locator('#formUpdateSchedule');await form.waitFor();qa.assert.equal(await form.locator('#appointment_id').inputValue(),String(appointmentId));
  await form.locator('#doctor_id_edit').selectOption('2',{force:true});await form.locator('#hora_cita_edit option[value="'+targetTime+'"]').waitFor({state:'attached'});await form.locator('#service_id_edit option[value="2"]').waitFor({state:'attached'});await form.locator('#service_id_edit').selectOption('2',{force:true});await form.locator('#hora_cita_edit').selectOption(targetTime,{force:true});
  const before=qa.readback(),response=page.waitForResponse(r=>r.url().endsWith('/admissionist/schedule/update')&&r.request().method()==='POST');await form.locator('input[type=submit]').click();const r=await response;qa.assert.equal(r.status(),200,JSON.stringify(await r.json()));await page.waitForTimeout(2200);
  const after=qa.readback(),a=after.appointments.find(x=>x.id===appointmentId);qa.assert.equal(a.hora_cita.slice(0,5),targetTime);qa.assert.equal(a.duracion_cita,60);qa.assert.equal(a.duration_slots,2);qa.assert.deepEqual(after.payments,before.payments);
 });
 await qa.finish();}catch(e){await qa.fail(e);}})();
