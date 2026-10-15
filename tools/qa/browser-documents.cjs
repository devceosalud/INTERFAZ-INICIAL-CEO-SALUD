const fs=require('node:fs'),{launch}=require('./browser-harness.cjs'),{helpers}=require('./browser-helpers.cjs');
(async()=>{const qa=await launch('documents');try{
 const ids=JSON.parse(fs.readFileSync(qa.out+'/jornada-ids.json','utf8')),{page:admin}=await qa.context('admin'),{page:n}=await qa.context('nicole'),{page:a}=await qa.context('commercialA'),h=helpers(qa);
 const base='/scheduling-mvp/agenda/appointments/'+ids.a+'/documents';let old,newDoc;
 await qa.step('Editor concede explícitamente VIEW y MANAGE de documentos a Admisión, sin añadir facultades financieras',admin,()=>h.permissions(admin,'ADMISION',{'appointment.documents.view':true,'appointment.documents.manage':true}));
 await h.agenda(n);await h.select(n,ids.a);await n.locator('#agenda-op-documents-panel > summary').click();
 await n.waitForFunction(()=>!document.getElementById('agenda-op-add-documents').disabled);
 // Preserve an interrupted attempt through the existing audited label editor; never delete its file.
 while(await n.getByRole('link',{name:'RECETA OPERATIVA FICTICIA QA',exact:true}).count()){
  const row=n.locator('.agenda-document-row').filter({hasText:'RECETA OPERATIVA FICTICIA QA'}).first();await row.locator('summary').click();
  await row.getByLabel('Etiqueta del documento',{exact:true}).fill('DOCUMENTO DE INTENTO QA ANTERIOR');
  const response=n.waitForResponse(r=>r.url().includes('/documents/')&&r.request().method()==='PUT');await row.getByRole('button',{name:'Guardar',exact:true}).click();qa.assert.equal((await response).status(),200);
  await n.waitForTimeout(300);
 }
 await qa.step('Nicole adjunta receta ficticia privada a la cita correcta desde Agenda',n,async()=>{
  await h.image(n,'DOCUMENTO OPERATIVO FICTICIO QA','0.00','NO ES UN PAGO','document-operational-qa.png');
  await n.locator('#agenda-op-document-kind').selectOption('PRESCRIPTION');await n.locator('#agenda-op-document-label').fill('RECETA OPERATIVA FICTICIA QA');
  await n.locator('#agenda-op-document-file').setInputFiles(qa.out+'/document-operational-qa.png');
  await n.locator('#agenda-op-add-documents').waitFor({state:'visible'});qa.assert.equal(await n.locator('#agenda-op-add-documents').isEnabled(),true,'Document upload must be enabled for the explicitly authorized actor');
  const response=n.waitForResponse(r=>r.url().endsWith(base)&&r.request().method()==='POST');response.catch(()=>{});await n.locator('#agenda-op-add-documents').click({timeout:5000});const r=await response;qa.assert.equal(r.status(),201);old=await r.json();
  qa.assert.equal(old.document_kind,'PRESCRIPTION');qa.assert.equal(old.private_path,undefined);qa.assert.equal(old.can_replace,true);qa.assert.equal(old.payment_id,null);
  await n.locator('a[href="'+old.download_url+'"]').waitFor();
 });
 await qa.step('Comercial sin permiso documental recibe 404; usar la cita equivocada tampoco revela el archivo',a,async()=>{
  const denied=await a.context().request.get(old.download_url,{headers:{Accept:'application/json'}});qa.assert.equal(denied.status(),404);
  const wrong=await n.context().request.get(qa.base+'/scheduling-mvp/agenda/appointments/'+ids.b+'/documents/'+old.id,{headers:{Accept:'application/json'}});qa.assert.equal(wrong.status(),404);
 });
 await qa.step('Reemplazo explícito con motivo crea nueva versión y conserva el archivo anterior fuera de descargas ordinarias',n,async()=>{
  await n.locator('.agenda-document-row[data-document-id="'+old.id+'"]').getByRole('button',{name:'Reemplazar archivo',exact:true}).click();
  await n.locator('#agenda-op-replacement-file').setInputFiles(qa.out+'/document-operational-qa.png');await n.locator('#agenda-op-replacement-reason').fill('QA: nueva versión operativa verificada');
  const response=n.waitForResponse(r=>r.url().endsWith('/'+old.id+'/replace')&&r.request().method()==='POST');await n.locator('#agenda-op-replacement-save').click();const r=await response;qa.assert.equal(r.status(),200,JSON.stringify(await r.json()));newDoc=await r.json();qa.assert.notEqual(newDoc.id,old.id);
  await n.locator('#agenda-op-replacement-panel').waitFor({state:'hidden'});qa.assert.equal((await n.context().request.get(old.download_url,{headers:{Accept:'application/json'}})).status(),404);
  qa.assert.equal((await n.context().request.get(newDoc.download_url)).status(),200);
 });
 await qa.step('Nicole muestra únicamente la versión vigente de la cita; revocar VIEW retira enlaces y acceso desde sesión existente',n,async()=>{
  await n.goto(qa.base+'/scheduling-mvp/admission');await n.locator('tr[data-appointment-id="'+ids.a+'"] button').click();
  await n.locator('#admission-detail').getByRole('link',{name:'Receta · RECETA OPERATIVA FICTICIA QA',exact:true}).waitFor();
  qa.assert.equal(await n.locator('#admission-detail').getByRole('link',{name:'Receta · RECETA OPERATIVA FICTICIA QA',exact:true}).count(),1);
  await h.permissions(admin,'ADMISION',{'appointment.documents.view':false});
  qa.assert.equal((await n.context().request.get(newDoc.download_url,{headers:{Accept:'application/json'}})).status(),404);
  await n.reload();await n.locator('tr[data-appointment-id="'+ids.a+'"] button').click();await n.getByText('Acceso restringido: requiere permiso para documentos privados.',{exact:true}).waitFor();
  await h.permissions(admin,'ADMISION',{'appointment.documents.view':true});
  const db=qa.readback();qa.assert.equal(db.historical_intact,true);qa.assert.equal(db.counts.payments,3);qa.assert.equal(Number(db.payment_total),100);h.save('documents-db',db);h.save('documents-ids',{old:old.id,current:newDoc.id,appointment:ids.a});
 });await qa.finish();
 }catch(e){await qa.fail(e);}})();
