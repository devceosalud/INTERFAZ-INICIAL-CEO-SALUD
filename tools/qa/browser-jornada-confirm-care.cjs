const fs=require('node:fs'),crypto=require('node:crypto'),{launch}=require('./browser-harness.cjs'),{helpers}=require('./browser-helpers.cjs');
(async()=>{const qa=await launch('jornada-confirm-care');try{
 const ids=JSON.parse(fs.readFileSync(qa.out+'/jornada-ids.json','utf8')),{page:a}=await qa.context('commercialA'),{page:b}=await qa.context('commercialB'),{page:n}=await qa.context('nicole'),h=helpers(qa);
 await h.agenda(a);await h.select(a,ids.a);await h.agenda(b);await h.select(b,ids.b);
 await qa.step('Adelanto S20 no permite confirmación ni altera el pago existente',a,async()=>{qa.assert.equal(await a.locator('#agenda-op-confirm-reservation').isDisabled(),true);const before=qa.readback();qa.assert.equal((await h.direct(a,'/scheduling-mvp/agenda/appointments/'+ids.a+'/payments','POST',{request_key:crypto.randomUUID(),confirm:true})).status,422);qa.assert.deepEqual(qa.readback().payments,before.payments);});
 async function ocrPayment(page,id,bank,amount,op){
  if(await page.locator('#agenda-op-documents-panel').getAttribute('open')===null)await page.locator('#agenda-op-documents-panel > summary').click();
  const file=await h.image(page,bank,amount,op,'receipt-'+bank.toLowerCase()+'-'+amount+'.png');await page.locator('#agenda-op-proof').setInputFiles(file);await page.getByRole('button',{name:'Leer imagen con OCR',exact:true}).click();await page.getByRole('button',{name:'Usar sugerencias en el pago',exact:true}).waitFor({timeout:90000});
  qa.assert.equal(await page.getByLabel('Monto sugerido',{exact:true}).inputValue(),amount);qa.assert.equal(await page.getByLabel('Operación sugerida',{exact:true}).inputValue(),op);await page.getByRole('button',{name:'Usar sugerencias en el pago',exact:true}).click();
  await page.locator('#agenda-op-proof-reviewed').check();const response=page.waitForResponse(r=>r.url().endsWith('/'+id+'/payments')&&r.request().method()==='POST');await page.locator('#agenda-op-submit-payment').click();const r=await response;qa.assert.equal(r.status(),200,JSON.stringify(await r.json()));
 }
 await qa.step('OCR BCP S30: segundo pago separado alcanza 50% sin confirmar automáticamente',a,async()=>{await ocrPayment(a,ids.a,'BCP','30.00','66000002');const db=qa.readback();qa.assert.equal(db.counts.payments,2);qa.assert.equal(Number(db.payment_total),50);qa.assert.equal(db.appointments.find(x=>x.id===ids.a).estado_agenda,'PENDIENTE_CONFIRMACION');h.save('jornada-after-50',db);});
 await qa.step('OCR BBVA S50 en ADICIONAL pendiente admite adelanto y conserva reserva',b,async()=>{await ocrPayment(b,ids.b,'BBVA','50.00','66000003');const db=qa.readback();qa.assert.equal(db.counts.payments,3);qa.assert.equal(Number(db.payment_total),100);qa.assert.equal(db.appointments.find(x=>x.id===ids.b).estado_agenda,'PENDIENTE_CONFIRMACION');});
 let inFlight=0,maxInFlight=0,requests=0,navigations=0;const ticks=[];
 n.on('request',r=>{if(r.url().includes('/admission/feed')){requests++;inFlight++;maxInFlight=Math.max(inFlight,maxInFlight);ticks.push(Date.now());}});n.on('requestfinished',r=>{if(r.url().includes('/admission/feed'))inFlight--;});n.on('requestfailed',r=>{if(r.url().includes('/admission/feed'))inFlight--;});
 await n.goto(qa.base+'/scheduling-mvp/admission');await n.locator('#admission-count').filter({hasText:'0'}).waitFor();n.on('framenavigated',frame=>{if(frame===n.mainFrame())navigations++;});
 await qa.step('Confirmación expresa de A; Nicole ve la cita mediante polling sin recarga',a,async()=>{
  await a.locator('.agenda-day-entry[data-appointment-id="'+ids.a+'"]').first().click();if(await a.locator('#agenda-op-payment-panel').getAttribute('open')===null)await a.locator('#agenda-op-payment-panel > summary').click();
  await a.waitForFunction(()=>!document.getElementById('agenda-op-confirm-reservation').disabled);const response=a.waitForResponse(r=>r.url().endsWith('/'+ids.a+'/payments')&&r.request().method()==='POST');const start=Date.now();await a.locator('#agenda-op-confirm-reservation').click();qa.assert.equal((await response).status(),200);
  await n.locator('#admission-rows tr[data-appointment-id="'+ids.a+'"]').waitFor({timeout:15000});const lag=Date.now()-start;qa.assert.equal(navigations,0);qa.assert.equal(maxInFlight,1);h.save('nicole-poll-jornada',{lag_ms:lag,requests,max_in_flight:maxInFlight,navigations,ticks});
 });
 await qa.step('Confirmación expresa de ADICIONAL mantiene tres pagos y dos citas distintas',b,async()=>{await b.waitForFunction(()=>!document.getElementById('agenda-op-confirm-reservation').disabled);const response=b.waitForResponse(r=>r.url().endsWith('/'+ids.b+'/payments')&&r.request().method()==='POST');await b.locator('#agenda-op-confirm-reservation').click();qa.assert.equal((await response).status(),200);const db=qa.readback();qa.assert.equal(db.appointments.find(x=>x.id===ids.b).estado_agenda,'CONFIRMADA');qa.assert.equal(db.counts.payments,3);});
 await qa.step('Nicole recorre llegada, llamado, inicio y fin con confirmación y persistencia',n,async()=>{
  await n.locator('#admission-rows tr[data-appointment-id="'+ids.a+'"] button').click();
  for(const [label,state] of [['Registrar llegada','EN_ESPERA'],['Registrar llamado','LLAMANDO'],['Iniciar atención','EN_ATENCION'],['Finalizar atención','ATENDIDO']]){
   await n.getByRole('button',{name:label,exact:true}).click();const response=n.waitForResponse(r=>r.url().endsWith('/'+ids.a+'/care')&&r.request().method()==='POST');await n.locator('.swal2-confirm').click();qa.assert.equal((await response).status(),200);qa.assert.equal(qa.readback().appointments.find(x=>x.id===ids.a).estado_cita,state);
  }
  await n.getByText('Historial administrativo · hasta 20 citas visibles',{exact:true}).click();await n.screenshot({path:qa.out+'/rc-nicole-jornada-attended.png',fullPage:true});
 });h.save('jornada-confirm-care-db',qa.readback());await qa.finish();
 }catch(e){await qa.fail(e);}})();
