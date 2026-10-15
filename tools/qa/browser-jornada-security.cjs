const fs=require('node:fs'),crypto=require('node:crypto'),{launch}=require('./browser-harness.cjs'),{helpers}=require('./browser-helpers.cjs');
(async()=>{const qa=await launch('jornada-security');try{
 const ids=JSON.parse(fs.readFileSync(qa.out+'/jornada-ids.json','utf8')),{page:admin}=await qa.context('admin'),{page:a}=await qa.context('commercialA'),{page:b}=await qa.context('commercialB'),h=helpers(qa);
 await h.agenda(a);await h.select(a,ids.a);await h.agenda(b);const oldState=await b.context().storageState();
 await qa.step('Edición operativa permitida guarda motivo y actor sin cambiar precio ni pagos',a,async()=>{
  await a.locator('#agenda-op-notes-panel > summary').click();await a.locator('#agenda-op-reason').fill('CONTROL ADMINISTRATIVO FICTICIO QA');
  const response=a.waitForResponse(r=>r.url().endsWith('/'+ids.a+'/notes')&&r.request().method()==='PATCH');await a.locator('#agenda-op-save-notes').click();qa.assert.equal((await response).status(),200);qa.assert.equal(qa.readback().counts.payments,3);
 });
 await qa.step('ADMIN retira UPDATE; interfaz abierta y petición directa ya no pueden editar',admin,async()=>{
  await h.permissions(admin,'COMERCIAL',{'appointment.update':false});const before=qa.readback();await a.locator('#agenda-op-note').fill('CAMBIO QUE DEBE SER RECHAZADO');const response=a.waitForResponse(r=>r.url().endsWith('/'+ids.a+'/notes')&&r.request().method()==='PATCH');await a.locator('#agenda-op-save-notes').click();qa.assert.equal((await response).status(),403);
  qa.assert.equal((await h.direct(a,'/scheduling-mvp/agenda/appointments/'+ids.a+'/notes','PATCH',{observaciones:'MANIPULADO QA'})).status,403);qa.assert.equal(qa.readback().counts.appointment_events,before.counts.appointment_events);qa.assert.deepEqual(qa.readback().payments,before.payments);
 });
 await qa.step('ADMIN retira PAY; formulario previamente habilitado no puede registrar otro pago',admin,async()=>{
  await h.agenda(a);await h.select(a,ids.b);qa.assert.equal(await a.locator('#agenda-op-amount').isEnabled(),true);await h.permissions(admin,'COMERCIAL',{'appointment.payment.submit':false});const before=qa.readback();
  await a.locator('#agenda-op-amount').fill('1');await a.locator('#agenda-op-method').selectOption('EFECTIVO',{force:true});const response=a.waitForResponse(r=>r.url().endsWith('/'+ids.b+'/payments')&&r.request().method()==='POST');await a.locator('#agenda-op-submit-payment').click();qa.assert.equal((await response).status(),403);
  qa.assert.equal((await h.direct(a,'/scheduling-mvp/agenda/appointments/'+ids.b+'/payments','POST',{request_key:crypto.randomUUID(),payment:{amount:'1',method:'EFECTIVO'}})).status,403);qa.assert.deepEqual(qa.readback().payments,before.payments);
 });
 await qa.step('Administrador desactiva Comercial B desde Usuarios con motivo; sesión activa queda inválida',admin,async()=>{
  await admin.goto(qa.base+'/admin/user/index');const row=admin.getByRole('row').filter({hasText:'rc-commercialb@example.invalid'});await row.getByRole('button',{name:'Desactivar',exact:true}).click();await admin.locator('.swal2-textarea').fill('PRUEBA QA: desactivación supervisada');const response=admin.waitForResponse(r=>r.url().endsWith('/4/access-state')&&r.request().method()==='POST');await admin.locator('.swal2-confirm').click();qa.assert.equal((await response).status(),200);await admin.locator('.swal2-confirm').click();await admin.waitForURL('**/admin/user/index');
  const r=await b.context().request.get(qa.base+'/scheduling-mvp/agenda/feed?vista=dia&fecha='+qa.fixture.date,{headers:{Accept:'application/json'}});qa.assert.ok([401,403].includes(r.status()));qa.assert.equal(qa.readback().worker_access.find(x=>x.id===4).is_active,0);
 });
 await qa.step('Reactivación conserva roles e historial y no revive la cookie anterior',admin,async()=>{
  const row=admin.getByRole('row').filter({hasText:'rc-commercialb@example.invalid'});await row.getByRole('button',{name:'Reactivar',exact:true}).click();await admin.locator('.swal2-textarea').fill('PRUEBA QA: reactivación autorizada');const response=admin.waitForResponse(r=>r.url().endsWith('/4/access-state')&&r.request().method()==='POST');await admin.locator('.swal2-confirm').click();qa.assert.equal((await response).status(),200);await admin.locator('.swal2-confirm').click();await admin.waitForURL('**/admin/user/index');
  const {context}=await qa.context('commercialB-old',{storageState:oldState});const r=await context.request.get(qa.base+'/scheduling-mvp/agenda/feed?vista=dia&fecha='+qa.fixture.date,{headers:{Accept:'application/json'}});qa.assert.ok([401,403].includes(r.status()));
  const db=qa.readback();qa.assert.equal(db.worker_access.find(x=>x.id===4).auth_version,2);qa.assert.equal(db.historical_intact,true);qa.assert.equal(db.counts.payments,3);qa.assert.equal(Number(db.payment_total),100);h.save('jornada-security-db',db);
 });await qa.step('Comercial B requiere inicio de sesión nuevo y recupera acceso ordinario',b,()=>qa.login(b,'rc-commercialb@example.invalid'));await qa.finish();
 }catch(e){await qa.fail(e);}})();
