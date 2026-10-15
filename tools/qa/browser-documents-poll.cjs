const fs=require('node:fs'),{launch}=require('./browser-harness.cjs'),{helpers}=require('./browser-helpers.cjs');
(async()=>{const qa=await launch('documents-poll');try{
 const {page:admin}=await qa.context('admin'),{page:n}=await qa.context('nicole'),{page:a}=await qa.context('commercialA'),h=helpers(qa),ids=JSON.parse(fs.readFileSync(qa.out+'/jornada-ids.json','utf8'));let first,current;const metrics={};const money=qa.readback().payments;
 await h.permissions(admin,'COMERCIAL',{'appointment.update':true,'appointment.documents.view':true,'appointment.documents.manage':true});
 await n.goto(qa.base+'/scheduling-mvp/admission');await n.locator('tr[data-appointment-id="'+ids.a+'"] button').click();await n.locator('#admission-detail h3').waitFor();let navigations=0;n.on('framenavigated',f=>{if(f===n.mainFrame())navigations++;});
 await h.agenda(a);await h.select(a,ids.a);await a.locator('#agenda-op-documents-panel > summary').click();await a.waitForFunction(()=>!document.getElementById('agenda-op-add-documents').disabled);
 await qa.step('Documento subido por Comercial aparece en el detalle de Nicole sin recargar ni cambiar filas',a,async()=>{
  const file=await h.image(a,'INFORME OPERATIVO FICTICIO QA','0.00','NO ES DINERO','document-poll-qa.png');await a.locator('#agenda-op-document-kind').selectOption('REPORT');await a.locator('#agenda-op-document-label').fill('INFORME SONDEO FICTICIO QA');await a.locator('#agenda-op-document-file').setInputFiles(file);
  const response=a.waitForResponse(r=>r.url().endsWith('/'+ids.a+'/documents')&&r.request().method()==='POST');const start=Date.now();await a.locator('#agenda-op-add-documents').click();const r=await response;qa.assert.equal(r.status(),201);first=await r.json();await n.bringToFront();
  await n.locator('#admission-detail a[href="'+first.download_url+'"]').waitFor({timeout:20000});metrics.upload_visible_ms=Date.now()-start;qa.assert.equal(navigations,0);
 });
 await qa.step('Versión reemplazada actualiza el enlace vigente mediante sondeo',a,async()=>{
  await a.locator('.agenda-document-row[data-document-id="'+first.id+'"]').getByRole('button',{name:'Reemplazar archivo',exact:true}).click();await a.locator('#agenda-op-replacement-file').setInputFiles(qa.out+'/document-poll-qa.png');await a.locator('#agenda-op-replacement-reason').fill('QA: segunda versión para sondeo');
  const response=a.waitForResponse(r=>r.url().endsWith('/'+first.id+'/replace')&&r.request().method()==='POST');const start=Date.now();await a.locator('#agenda-op-replacement-save').click();const r=await response;qa.assert.equal(r.status(),200);current=await r.json();await n.bringToFront();
  await n.locator('#admission-detail a[href="'+current.download_url+'"]').waitFor({timeout:20000});qa.assert.equal(await n.locator('#admission-detail a[href="'+first.download_url+'"]').count(),0);metrics.replacement_visible_ms=Date.now()-start;qa.assert.equal(navigations,0);
 });
 await qa.step('Revocar VIEW retira documentos del panel abierto con la siguiente revalidación autorizada',admin,async()=>{
  await h.permissions(admin,'ADMISION',{'appointment.documents.view':false});await n.bringToFront();await n.getByText('Acceso restringido: requiere permiso para documentos privados.',{exact:true}).waitFor({timeout:20000});qa.assert.equal((await n.context().request.get(current.download_url,{headers:{Accept:'application/json'}})).status(),404);await h.permissions(admin,'ADMISION',{'appointment.documents.view':true});
  qa.assert.equal(navigations,0);qa.assert.deepEqual(qa.readback().payments,money);qa.assert.equal(qa.readback().historical_intact,true);
 });metrics.navigations=navigations;h.save('documents-poll-metrics',metrics);await qa.finish();
 }catch(e){await qa.fail(e);}})();
