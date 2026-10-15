const fs=require('node:fs'),{launch}=require('./browser-harness.cjs');
(async()=>{const qa=await launch('permissions-schedules');try{
 const {page:admin}=await qa.context('admin'),{page:a}=await qa.context('commercialA'),{page:b}=await qa.context('commercialB');
 await admin.goto(qa.base+'/admin/role');await a.goto(qa.base+'/scheduling-mvp/agenda');await b.goto(qa.base+'/scheduling-mvp/agenda');
 const direct=(page,url,method='POST',data={})=>page.evaluate(async({url,method,data})=>{const r=await fetch(url,{method,headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify(data)});return {status:r.status,body:await r.json().catch(()=>({non_json:true}))};},{url,method,data});
 async function permissions(role,changes){await admin.goto(qa.base+'/admin/role/edit/'+qa.fixture.roles[role]);for(const [cap,enabled]of Object.entries(changes))await admin.getByLabel(cap,{exact:true}).setChecked(enabled);admin.once('dialog',dialog=>dialog.accept());await Promise.all([admin.waitForURL('**/admin/role'),admin.getByRole('button',{name:'Actualizar Permisos',exact:true}).or(admin.locator('input[value="Actualizar Permisos"]')).click()]);}
 await permissions('COMERCIAL',{'appointment.create':false,'appointment.update':false,'doctor.schedule.manage':false});
 await a.reload();
 await qa.step('Comercial sin CREATE: interfaz bloqueada y petición directa 403, cero dinero',a,async()=>{
  qa.assert.equal((await direct(a,'/scheduling-mvp/agenda/registrations')).status,403);qa.assert.equal(qa.readback().counts.payments,0);
 });
 await qa.step('Horarios: comercial sin capacidad recibe 403 en crear, editar e inactivar',a,async()=>{
  for(const [url,method] of [['store','POST'],['update','PUT'],['delete','POST']])qa.assert.equal((await direct(a,'/admissionist/doctor-schedule/'+url,method)).status,403);
 });
 await qa.step('ADMIN concede CREATE, UPDATE y gestión de horarios mediante editor existente',admin,()=>permissions('COMERCIAL',{'appointment.create':true,'appointment.update':true,'doctor.schedule.manage':true}));
 await qa.step('Sesión comercial existente recibe capacidades nuevas al refrescar sin evadir validaciones',a,async()=>{await a.reload();qa.assert.equal((await direct(a,'/scheduling-mvp/agenda/registrations')).status,422);const legacy=await direct(a,'/admissionist/doctor-schedule/store');qa.assert.equal(legacy.status,200);qa.assert.equal(legacy.body.code,0);qa.assert.equal(qa.readback().schedule_versions.length,0);});
 await qa.step('Comercial crea horario desde formulario real con sede, médico y cadencia',a,async()=>{
  await a.goto(qa.base+'/admissionist/doctor-schedule');await a.locator('#schedule-add').click();
  await a.locator('#schedule-doctor').selectOption('2');await a.locator('#schedule-site').selectOption(String(qa.fixture.site_id));
  await a.locator('#schedule-start-hour').selectOption('08');await a.locator('#schedule-start-minute').selectOption('00');await a.locator('#schedule-end-hour').selectOption('18');await a.locator('#schedule-end-minute').selectOption('00');await a.locator('#schedule-duration').selectOption('30');
  const response=a.waitForResponse(r=>r.url().endsWith('/doctor-schedule/store')&&r.request().method()==='POST');await a.locator('#schedule-save').click();await a.locator('.swal2-confirm').click();const result=await response;qa.assert.equal(result.status(),200,JSON.stringify(await result.json()));
  await a.locator('#schedule-editor').waitFor({state:'hidden'});await a.locator('.swal2-container').waitFor({state:'hidden'});
  await a.locator('.fc-event').first().waitFor();await a.screenshot({path:qa.out+'/rc-schedule-created.png',fullPage:true});
 });
 await qa.step('Calendarios de horarios Día, Semana y Mes representan el bloque persistido',a,async()=>{for(const view of ['timeGridDay','timeGridWeek','dayGridMonth']){await a.locator('[data-calendar-view="'+view+'"]').click();await a.locator('.fc-event').first().waitFor();}});
 await qa.step('ADMIN revoca horarios y CREATE; misma sesión rechazada en backend',admin,async()=>{await permissions('COMERCIAL',{'appointment.create':false,'doctor.schedule.manage':false});qa.assert.equal((await direct(a,'/admissionist/doctor-schedule/store')).status,403);qa.assert.equal((await direct(a,'/scheduling-mvp/agenda/registrations')).status,403);});
 await qa.step('ADMIN restituye únicamente CREATE para continuar la jornada',admin,()=>permissions('COMERCIAL',{'appointment.create':true}));
 fs.writeFileSync(qa.out+'/permissions-schedules-db.json',JSON.stringify(qa.readback(),null,2));await qa.finish();
 }catch(e){await qa.fail(e);}})();
