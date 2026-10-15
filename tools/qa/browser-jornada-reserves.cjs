const {launch}=require('./browser-harness.cjs'),{helpers}=require('./browser-helpers.cjs');
(async()=>{const qa=await launch('jornada-reserves');try{
 const {page:admin}=await qa.context('admin'),{page:a}=await qa.context('commercialA'),{page:b}=await qa.context('commercialB'),h=helpers(qa);const ids={};
 await h.agenda(a);await h.agenda(b);
 await qa.step('Comercial A crea paciente ficticio y reserva sin dinero desde modal',a,async()=>{
  await a.locator('.agenda-day-row[data-row-start="09:00"] .agenda-day-entry').first().click();
  await a.locator('#agenda-document-number').fill('72000001');await a.locator('#agenda-document-search').click();await a.locator('#agenda-patient-register').click();
  await a.locator('#agenda-draft-nombre').fill('PACIENTE JORNADA QA');await a.locator('#agenda-draft-apellido-paterno').fill('FICTICIO');await a.locator('#agenda-draft-apellido-materno').fill('PRUEBA');
  await a.locator('#agenda-draft-telefono').fill('999000001');await a.locator('#agenda-draft-genero').selectOption('HOMBRE',{force:true});
  await a.locator('#agenda-draft-more > summary').click();await a.locator('#agenda-draft-phone-secondary').fill('+51999000002');
  await a.locator('#agenda-draft-service').selectOption('2',{force:true});
  await a.waitForFunction(()=>document.getElementById('agenda-op-price').value==='100.00'&&document.getElementById('agenda-op-quote-status').hidden);
  const response=a.waitForResponse(r=>r.url().endsWith('/agenda/registrations')&&r.request().method()==='POST');await a.locator('#agenda-draft-save').click();const r=await response;const body=await r.json();qa.assert.equal(r.status(),201,JSON.stringify(body));ids.a=body.appointment.appointment_id;
  qa.assert.equal(qa.readback().counts.payments,0);const saved=qa.readback().appointments.find(x=>x.id===ids.a);qa.assert.equal(saved.estado_agenda,'PENDIENTE_CONFIRMACION');qa.assert.equal(saved.responsible_user_id,3);
 });
 h.save('jornada-ids',ids);
 await qa.step('Comercial B no ve identidad de A y reserva el mismo intervalo todavía libre',b,async()=>{
  await b.reload();await b.locator('#agenda-doctor-2').check({force:true});const row=b.locator('.agenda-day-row[data-row-start="09:00"]');await row.waitFor();qa.assert.equal((await b.locator('body').innerText()).includes('PACIENTE JORNADA QA'),false);
  await row.locator('.agenda-day-entry').first().click();await b.locator('#agenda-document-number').fill('70000001');await b.locator('#agenda-document-search').click();await b.locator('#agenda-quick-patient-id').waitFor({state:'attached'});await b.waitForFunction(()=>document.getElementById('agenda-quick-patient-id').value==='1');
  const response=b.waitForResponse(r=>r.url().endsWith('/agenda/registrations')&&r.request().method()==='POST');await b.locator('#agenda-pending-start').click();const r=await response;const body=await r.json();qa.assert.equal(r.status(),201,JSON.stringify(body));ids.b=body.appointment.appointment_id;
  qa.assert.equal(qa.readback().counts.payments,0);h.save('jornada-ids',ids);
 });
 await qa.step('Sin permiso financiero: campo y cobro HTTP rechazados; CSRF real rechaza 419',a,async()=>{
  await h.select(a,ids.a);qa.assert.equal(await a.locator('#agenda-op-amount').isDisabled(),true);
  const data={request_key:require('node:crypto').randomUUID(),payment:{amount:'20',method:'EFECTIVO'}};
  qa.assert.equal((await h.direct(a,'/scheduling-mvp/agenda/appointments/'+ids.a+'/payments','POST',data)).status,403);
  qa.assert.equal((await h.direct(a,'/scheduling-mvp/agenda/appointments/'+ids.a+'/payments','POST',data,false)).status,419);qa.assert.equal(qa.readback().counts.payments,0);
 });
 await qa.step('Administrador habilita cobros mediante editor, comercial refresca y puede ingresar adelanto',admin,async()=>{await h.permissions(admin,'COMERCIAL',{'appointment.payment.submit':true});await h.agenda(a);await h.select(a,ids.a);qa.assert.equal(await a.locator('#agenda-op-amount').isEnabled(),true);});
 h.save('jornada-reserves-db',qa.readback());await qa.finish();
 }catch(e){await qa.fail(e);}})();
