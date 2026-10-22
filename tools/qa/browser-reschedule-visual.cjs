const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require(path.join(process.env.USERPROFILE,'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'));
const root=path.resolve(__dirname,'../..'),out=path.join(root,'storage/app/qa-reschedule-visual');
const read=name=>JSON.parse(fs.readFileSync(path.join(out,name),'utf8'));
const fixture=read('browser-fixture.json'),secret=read('browser-secret.json'),base='http://127.0.0.1:8099';
(async()=>{
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true,args:['--disable-background-networking','--host-resolver-rules=MAP * 0.0.0.0, EXCLUDE 127.0.0.1, EXCLUDE localhost']});
 const context=await browser.newContext({viewport:{width:1440,height:1000},timezoneId:'America/Lima'});
 await context.route('**/*',r=>['127.0.0.1','localhost'].includes(new URL(r.request().url()).hostname)?r.continue():r.abort());
 const page=await context.newPage();page.setDefaultTimeout(20000);
 const report={browser:browser.version(),cases:[],errors:[]};page.on('pageerror',e=>report.errors.push(e.message));
 const save=()=>fs.writeFileSync(path.join(out,'browser-results.json'),JSON.stringify(report,null,2));
 async function step(name,fn){try{await fn();report.cases.push({name,result:'PASS'});console.log('PASS '+name);save();}catch(e){report.cases.push({name,result:'FAIL',message:String(e.message).replaceAll(secret.password,'[REDACTED]')});save();throw e;}}
 async function direct(url,method='GET',data){return page.evaluate(async({url,method,data})=>{const r=await fetch(url,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},...(data?{body:JSON.stringify(data)}:{})});return {status:r.status,body:await r.json()};},{url,method,data});}
 const agenda=date=>base+'/scheduling-mvp/agenda?fecha='+date+'&doctor_id='+fixture.doctor_id;
 const feed=view=>'/scheduling-mvp/agenda/feed?vista='+view+'&fecha=2026-10-21&doctor_id[]='+fixture.doctor_id;
 const detail='/scheduling-mvp/agenda/appointments/'+fixture.appointment_id+'/quick-edit';
 async function board(date='2026-10-21'){await page.goto(agenda(date));await page.locator('#agenda-day-grid-body .agenda-day-row').first().waitFor();}
 async function shot(name){await page.screenshot({path:path.join(out,name),fullPage:true});}
 try{
  await page.goto(base+'/');await page.locator('[name=email]').fill(secret.email);await page.locator('[name=password]').fill(secret.password);
  await Promise.all([page.waitForURL('**/dashboard'),page.getByRole('button',{name:'Iniciar sesión',exact:true}).click()]);
  await step('Antes: cita vigente en 12:20 con S/60 reales',async()=>{
   await board();await page.locator('.agenda-day-entry[data-appointment-id="'+fixture.appointment_id+'"]').first().waitFor();
   assert.equal(await page.locator('.agenda-rescheduled-marker').count(),0);await shot('01-antes-agenda-desktop.png');
  });
  await step('Reprogramar por backend real con revision y CSRF conserva el pago',async()=>{
   const d=await direct(detail);assert.equal(d.status,200);const revision=d.body.revision||d.body.appointment?.revision;
   const move=await direct('/scheduling-mvp/agenda/appointments/'+fixture.appointment_id+'/reschedule','PATCH',{
    fecha_cita:'2026-10-24',hora_cita:'12:00',expected_fecha_cita:'2026-10-21',expected_hora_cita:'12:20',expected_revision:revision,motivo:'Cambio FICTICIO de fecha para QA visual'});
   assert.equal(move.status,200,JSON.stringify(move.body));
   await board();const marker=page.locator('.agenda-day-row[data-row-start="12:20"] .agenda-rescheduled-marker');await marker.waitFor();
   assert.match(await marker.innerText(),/REPROGRAMADA \| 21\/10\/2026 12:20–12:40 → 24\/10\/2026 12:00/);assert.match(await marker.innerText(),/liberado y disponible/);
   assert.equal(await marker.getAttribute('data-appointment-id'),null);assert.equal(await page.locator('#agenda-total-busy').innerText(),'0');
   await marker.scrollIntoViewIfNeeded();await shot('02-despues-agenda-desktop.png');
   await page.setViewportSize({width:390,height:844});await marker.scrollIntoViewIfNeeded();await shot('03-despues-agenda-mobile.png');
   assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth)<=391);await page.setViewportSize({width:1440,height:1000});
  });
  await step('Admisión: referencia sin cola, KPI ni ingresos en la fecha original',async()=>{
   await page.goto(base+'/scheduling-mvp/admission');await page.locator('#admission-reschedule-panel:not([hidden])').waitFor();
   await page.locator('#admission-reschedule-panel summary').click();
   const historical=page.locator('.admission-reschedule-reference');assert.equal(await historical.count(),1);assert.match(await historical.innerText(),/REPROGRAMADA/);
   assert.equal(await historical.locator('button,input').count(),0);assert.equal(await page.locator('#admission-rows [data-appointment-id]').count(),0);
   const payload=await direct('/scheduling-mvp/admission/feed','POST',{fecha:'2026-10-21',site_id:null});assert.equal(payload.body.kpis.total,0);assert.equal(payload.body.totals.pagos_reales,'0.00');
   await shot('04-admision-historial-desktop.png');await page.setViewportSize({width:390,height:844});await shot('05-admision-historial-mobile.png');
   assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth)<=391);await page.setViewportSize({width:1440,height:1000});
   await historical.getByRole('link',{name:'Ver cita reprogramada'}).click();await page.locator('.admission-reschedule-origin').waitFor();
   const current=await direct('/scheduling-mvp/admission/feed','POST',{fecha:'2026-10-24',site_id:null});assert.equal(current.body.kpis.total,1);assert.equal(current.body.totals.pagos_reales,'60.00');
   assert.equal(current.body.rows[0].reschedule_origin.from_time,'12:20');assert.equal(await page.locator('.admission-reschedule-origin').evaluate(e=>e.closest('td').cellIndex),1);await shot('06-admision-cita-vigente.png');
  });
  await step('Semana y mes: insignias DOM accesibles separadas de eventos reales',async()=>{
   await board();for(const view of ['semana','mes']){
    await page.locator('.agenda-btn--view[data-view="'+view+'"]').click();await page.locator('#agenda-calendar .agenda-history-count').first().waitFor();
    const response=await direct(feed(view));assert.equal(response.body.reprogramaciones.items.length,1);
    assert.ok(response.body.eventos.every(e=>!String(e.title||'').includes('REPROGRAMADA')));
    await page.locator('#agenda-calendar .agenda-history-count').first().focus();await shot(view==='mes'?'08-mes.png':'07-semana.png');
   }
  });
  await step('Nueva cita confirmada reutiliza 12:20 y conserva la marca histórica secundaria',async()=>{
   await board();const create=await direct('/scheduling-mvp/agenda/registrations','POST',{request_key:crypto.randomUUID(),mode:'CONFIRM',booking_type:'REGULAR',
    patient_id:fixture.patient_id,doctor_id:fixture.doctor_id,service_id:fixture.service_id,fecha_cita:'2026-10-21',hora_cita:'12:20',duracion_cita:20,
    motivo_consulta:'Nueva cita FICTICIA en intervalo liberado',payment:{amount:'20',method:'EFECTIVO'}});assert.equal(create.status,201,JSON.stringify(create.body));
   await board();const row=page.locator('.agenda-day-row[data-row-start="12:20"]');await row.locator('.agenda-rescheduled-marker').waitFor();
   assert.ok(await row.locator('.agenda-day-entry[data-appointment-id]').count()>0);assert.doesNotMatch(await row.locator('.agenda-rescheduled-marker').innerText(),/liberado y disponible/);
   assert.equal(await page.locator('#agenda-total-busy').innerText(),'1');await row.scrollIntoViewIfNeeded();await shot('09-intervalo-reutilizado.png');
  });
  await step('Segunda reprogramación conserva ambos orígenes y el enlace al destino vigente',async()=>{
   const move=await direct('/scheduling-mvp/agenda/appointments/'+fixture.appointment_id+'/reschedule','PATCH',{fecha_cita:'2026-10-26',hora_cita:'12:40',
    expected_fecha_cita:'2026-10-24',expected_hora_cita:'12:00',motivo:'Segunda reprogramacion FICTICIA'});assert.equal(move.status,200,JSON.stringify(move.body));
   await board();const marker=page.locator('.agenda-day-row[data-row-start="12:20"] .agenda-rescheduled-marker');await marker.waitFor();assert.match(await marker.innerText(),/Cita vigente: 26\/10\/2026, 12:40/);
   const url=await marker.getByRole('button',{name:'Ver cita reprogramada'});await url.click();await page.waitForURL('**/scheduling-mvp/agenda?**fecha=2026-10-26**');
   await page.locator('.agenda-day-entry[data-appointment-id="'+fixture.appointment_id+'"]').first().waitFor();await shot('10-destino-vigente.png');
  });
  assert.deepEqual(report.errors,[]);save();
 }finally{await browser.close();}
})().catch(e=>{console.error(String(e.message).replaceAll(secret.password,'[REDACTED]'));process.exitCode=1;});
