const fs=require('node:fs'),crypto=require('node:crypto'),{launch}=require('./browser-harness.cjs'),{helpers}=require('./browser-helpers.cjs');
(async()=>{const qa=await launch('jornada-conversion');try{
 const ids=JSON.parse(fs.readFileSync(qa.out+'/jornada-ids.json','utf8')),{page:admin}=await qa.context('admin'),{page:b}=await qa.context('commercialB'),h=helpers(qa);
 await h.agenda(b);await h.select(b,ids.b);
 await qa.step('Reserva en conflicto ofrece resolución sin convertir; ADD ausente bloquea UI y HTTP',b,async()=>{
  await b.locator('#agenda-op-convert-additional').waitFor();qa.assert.equal(await b.locator('#agenda-op-convert-additional').isDisabled(),true);
  const u='/scheduling-mvp/agenda/appointments/'+ids.b+'/additional-conversion';const r=await b.context().request.get(qa.base+u,{headers:{Accept:'application/json'}});qa.assert.equal(r.status(),200);const offer=await r.json();qa.assert.equal(offer.available,true);qa.assert.equal(offer.price,'100.00');
  const before=qa.readback();qa.assert.equal((await h.direct(b,u,'POST',{request_key:crypto.randomUUID(),expected_version:offer.expected_version,confirmed:true})).status,403);qa.assert.deepEqual(qa.readback().payments,before.payments);
 });
 await qa.step('ADMIN concede ADD expresamente y la conversión exige confirmación visible de precio',admin,async()=>{
  await h.permissions(admin,'COMERCIAL',{'appointment.additional.create':true});await h.agenda(b);await h.select(b,ids.b);await b.locator('#agenda-op-convert-additional').click();await b.locator('.swal2-title').getByText('¿Conservar esta reserva como ADICIONAL?',{exact:true}).waitFor();
  qa.assert.equal((await b.locator('.swal2-html-container').innerText()).includes('100.00'),true);await b.locator('.swal2-cancel').click();qa.assert.equal(qa.readback().appointments.find(x=>x.id===ids.b).tipo_agendamiento,'REGULAR');
  const before=qa.readback();const response=b.waitForResponse(r=>r.url().endsWith('/'+ids.b+'/additional-conversion')&&r.request().method()==='POST');await b.locator('#agenda-op-convert-additional').click();await b.locator('.swal2-confirm').click();const r=await response;qa.assert.equal(r.status(),200,JSON.stringify(await r.json()));
  const after=qa.readback();qa.assert.equal(after.appointments.length,before.appointments.length);qa.assert.deepEqual(after.payments,before.payments);const a=after.appointments.find(x=>x.id===ids.b);qa.assert.equal(a.tipo_agendamiento,'ADICIONAL');qa.assert.equal(a.estado_agenda,'PENDIENTE_CONFIRMACION');qa.assert.equal(Number(a.precio_programado),100);h.save('jornada-after-conversion',after);
 });await qa.finish();
 }catch(e){await qa.fail(e);}})();
