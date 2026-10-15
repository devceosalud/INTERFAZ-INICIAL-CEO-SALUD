const fs=require('node:fs'),crypto=require('node:crypto'),{launch}=require('./browser-harness.cjs'),{helpers}=require('./browser-helpers.cjs');
(async()=>{const qa=await launch('jornada-pay');try{
 const ids=JSON.parse(fs.readFileSync(qa.out+'/jornada-ids.json','utf8'));
 const {page:admin}=await qa.context('admin'),{page:a}=await qa.context('commercialA'),{page:b}=await qa.context('commercialB'),{page:n}=await qa.context('nicole'),h=helpers(qa);
 await h.permissions(admin,'COMERCIAL',{'appointment.payment.submit':false});
 await h.agenda(a);await h.agenda(b);await n.goto(qa.base+'/scheduling-mvp/admission');
 await qa.step('Cobro sin permiso bloqueado por UI y backend; petición sin CSRF rechazada',a,async()=>{
  await h.select(a,ids.a);qa.assert.equal(await a.locator('#agenda-op-amount').isDisabled(),true);const d={request_key:crypto.randomUUID(),payment:{amount:'20',method:'EFECTIVO'}};
  qa.assert.equal((await h.direct(a,'/scheduling-mvp/agenda/appointments/'+ids.a+'/payments','POST',d)).status,403);
  qa.assert.equal((await h.direct(a,'/scheduling-mvp/agenda/appointments/'+ids.a+'/payments','POST',d,false)).status,419);qa.assert.equal(qa.readback().counts.payments,0);
 });
 await qa.step('Permiso de cobro concedido desde editor y efectivo tras refrescar sesión comercial',admin,async()=>{await h.permissions(admin,'COMERCIAL',{'appointment.payment.submit':true});await h.agenda(a);await h.select(a,ids.a);qa.assert.equal(await a.locator('#agenda-op-amount').isEnabled(),true);});
 await qa.step('OCR Yape local sugiere datos de comprobante ficticio y no registra dinero',a,async()=>{
  await a.locator('#agenda-op-documents-panel > summary').click();
  const image=await h.image(a,'YAPE','20.00','66000001','receipt-yape-20.png');await a.locator('#agenda-op-proof').setInputFiles(image);
  await a.getByRole('button',{name:'Leer imagen con OCR',exact:true}).click();await a.getByRole('button',{name:'Usar sugerencias en el pago',exact:true}).waitFor({timeout:90000});
  qa.assert.equal(await a.getByLabel('Monto sugerido',{exact:true}).inputValue(),'20.00');qa.assert.equal(await a.getByLabel('Operación sugerida',{exact:true}).inputValue(),'66000001');
  await a.getByRole('button',{name:'Usar sugerencias en el pago',exact:true}).click();qa.assert.equal(qa.readback().counts.payments,0);
  await a.locator('#agenda-op-submit-payment').click();await a.getByText('Revisa todos los comprobantes y confirma la revisión humana antes de registrar el pago.',{exact:true}).waitFor();qa.assert.equal(qa.readback().counts.payments,0);
 });
 await qa.step('Revisión humana explícita y adelanto S20: reserva protegida y todavía pendiente',a,async()=>{
  await a.locator('#agenda-op-proof-reviewed').check();const response=a.waitForResponse(r=>r.url().endsWith('/'+ids.a+'/payments')&&r.request().method()==='POST');await a.locator('#agenda-op-submit-payment').click();const r=await response;qa.assert.equal(r.status(),200,JSON.stringify(await r.json()));
  const db=qa.readback();qa.assert.equal(db.counts.payments,1);qa.assert.equal(Number(db.payment_total),20);qa.assert.equal(db.appointments.find(x=>x.id===ids.a).estado_agenda,'PENDIENTE_CONFIRMACION');qa.assert.equal(db.proofs.length,1);qa.assert.equal(db.proofs[0].payment_id,db.payments[0].id);h.save('jornada-after-20',db);
 });
 await qa.step('Comercial B conserva reserva y no puede cobrar el intervalo protegido ni conocer la reserva de A',b,async()=>{
  await h.agenda(b);await h.select(b,ids.b);await b.locator('#agenda-op-convert-additional').waitFor();qa.assert.equal(await b.locator('#agenda-op-convert-additional').isDisabled(),true);
  const before=qa.readback();const r=await h.direct(b,'/scheduling-mvp/agenda/appointments/'+ids.b+'/payments','POST',{request_key:crypto.randomUUID(),payment:{amount:'50',method:'EFECTIVO'}});qa.assert.equal(r.status,409);qa.assert.equal(JSON.stringify(r.body).includes('PACIENTE JORNADA QA'),false);qa.assert.deepEqual(qa.readback().payments,before.payments);
 });
 h.save('jornada-pay-db',qa.readback());await a.screenshot({path:qa.out+'/rc-advance-20.png',fullPage:true});await qa.finish();
 }catch(e){await qa.fail(e);}})();
