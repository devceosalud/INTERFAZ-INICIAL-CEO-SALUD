const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require(path.join(process.env.USERPROFILE,'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'));
const root=path.resolve(__dirname,'../..'),qa=path.join(root,'storage/app/qa-reschedule-visual'),out=path.join(qa,'medical-evidence');
fs.mkdirSync(out,{recursive:true});
const fixture=JSON.parse(fs.readFileSync(path.join(qa,'browser-fixture.json'),'utf8')),secret=JSON.parse(fs.readFileSync(path.join(qa,'browser-secret.json'),'utf8'));
const base='http://127.0.0.1:8099',id=fixture.appointment_id;
const report={browser:null,scope:'FICTITIOUS_LOCAL_ONLY',cases:[],errors:[],screenshots:[]};
const save=()=>fs.writeFileSync(path.join(out,'browser-results.json'),JSON.stringify(report,null,2));
const redact=value=>String(value).replaceAll(secret.password,'[REDACTED]');
async function step(name,fn){try{await fn();report.cases.push({name,result:'PASS'});console.log('PASS '+name);save();}catch(e){report.cases.push({name,result:'FAIL',message:redact(e.message)});save();throw e;}}
async function shot(page,name){await page.evaluate(()=>window.scrollTo(0,0));await page.waitForTimeout(150);if(await page.locator('#main-wrapper').count()){await page.locator('#main-wrapper.show').waitFor();await page.waitForFunction(()=>Number(getComputedStyle(document.querySelector('#main-wrapper')).opacity)===1);} const file=name+'.png';await page.screenshot({path:path.join(out,file),fullPage:true});report.screenshots.push(file);}
async function call(page,url,method='GET',data){return page.evaluate(async({url,method,data})=>{
 const response=await fetch(url,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},...(data?{body:JSON.stringify(data)}:{})});
 return {status:response.status,body:await response.json()};
},{url,method,data});}
(async()=>{
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true,
  args:['--disable-background-networking','--host-resolver-rules=MAP * 0.0.0.0, EXCLUDE 127.0.0.1, EXCLUDE localhost']});
 report.browser=browser.version();
 async function session(email){
  const context=await browser.newContext({viewport:{width:1440,height:1000},timezoneId:'America/Lima'});
  await context.route('**/*',r=>['127.0.0.1','localhost'].includes(new URL(r.request().url()).hostname)?r.continue():r.abort());
  const page=await context.newPage();page.setDefaultTimeout(20000);page.on('pageerror',e=>report.errors.push(redact(e.message)));
  await page.goto(base+'/');await page.locator('input[name=email]').fill(email);await page.locator('input[name=password]').fill(secret.password);
  await Promise.all([page.waitForURL(u=>!u.pathname.endsWith('/admin/SingIn')&&u.pathname!=='/'),page.getByRole('button',{name:'Iniciar sesión',exact:true}).click()]);
  return {context,page};
 }
 let admin,admission,medical;
 const medicalRow=()=>medical.page.locator('#medical-rows tr[data-appointment-id="'+id+'"]');
 const admissionRow=()=>admission.page.locator('#admission-rows tr[data-appointment-id="'+id+'"]');
 let cancelled=false;
 try{
  admin=await session(fixture.admin_email);admin.page.on('dialog',d=>d.accept());
  await step('Administrador revisa una cuenta existente sin crear duplicados ni asignar por coincidencias',async()=>{
   await admin.page.goto(base+'/admin/user/index');await admin.page.getByText('CUENTA EXISTENTE MEDICO FICTICIO',{exact:true}).waitFor();
   await shot(admin.page,'01-antes-cuenta-existente');
   await admin.page.goto(base+'/admin/user/'+fixture.existing_id+'/doctor-link');
   await admin.page.locator('[name=doctor_id]').selectOption(String(fixture.doctor_id));
   await admin.page.locator('[name=reason]').fill('Identidad FICTICIA verificada expresamente por administrador');
   await admin.page.locator('[name=identity_confirmed]').check();
   const wait=admin.page.waitForResponse(r=>r.url().endsWith('/doctor-link')&&r.request().method()==='POST');
   await admin.page.getByRole('button',{name:'Revisar vinculación',exact:true}).click();assert.equal((await wait).status(),200);
   await admin.page.waitForFunction(id=>document.querySelector('#user-doctor-link')?.dataset.current===String(id),fixture.doctor_id);
   await shot(admin.page,'02-despues-vinculo-auditado');
   await admin.page.goto(base+'/admin/user/edit/'+fixture.existing_id);
   await admin.page.locator('input[name=role][value="'+fixture.medical_role_id+'"]').check();
   await Promise.all([admin.page.waitForURL('**/admin/user/index'),admin.page.locator('input[type=submit]').click()]);
   await admin.page.goto(base+'/admin/role/edit/'+fixture.medical_role_id);
   await admin.page.getByText('Consultar mis citas médicas',{exact:true}).waitFor();await shot(admin.page,'03-permisos-medicos-minimos');
  });
  await step('Editor advierte que MEDICO reemplaza los roles existentes sin cambiar la cuenta al consultar',async()=>{
   await admin.page.goto(base+'/admin/user/edit/'+fixture.actor_id);
   await admin.page.locator('#medical-role-replacement-warning').waitFor();
   assert.match(await admin.page.locator('#medical-role-replacement-warning').textContent(),/ADMISION/);
   assert.match(await admin.page.locator('#medical-role-replacement-warning').textContent(),/cuenta clínica personal separada/);
   await shot(admin.page,'03a-advertencia-reemplazo-rol');
  });
  admission=await session(secret.email);medical=await session(fixture.existing_email);
  medical.page.on('dialog',async d=>{if(cancelled){cancelled=false;await d.dismiss();}else await d.accept(d.type()==='prompt'?'Indicación médica FICTICIA para completar pruebas':undefined);});
  await step('Médico no inicia antes de llegada; cancelación de confirmación no guarda cambios',async()=>{
   await medical.page.goto(base+'/scheduling-mvp/medical/');await medicalRow().waitFor();await medicalRow().click();
   await medical.page.locator('#medical-detail h3').waitFor();
   assert.equal(await medical.page.locator('[data-medical-action=start]').count(),0);await shot(medical.page,'04-antes-llegada-medico');
   await admission.page.goto(base+'/scheduling-mvp/admission?fecha=2026-10-21');await admissionRow().waitFor();await admissionRow().click();
   await admission.page.getByRole('button',{name:'Registrar llegada',exact:true}).click();
   const wait=admission.page.waitForResponse(r=>r.url().endsWith('/care')&&r.request().method()==='POST');
   await admission.page.locator('.swal2-confirm').click();assert.equal((await wait).status(),200);
   await medical.page.locator('#medical-filters button[type=submit]').click();await medicalRow().waitFor();await medicalRow().click();
   await medical.page.locator('[data-medical-action=start]').waitFor();
   let posts=0;const count=r=>{if(r.url().includes('/care/start'))posts++;};medical.page.on('request',count);
   cancelled=true;await medical.page.locator('[data-medical-action=start]').click();await medical.page.waitForTimeout(150);assert.equal(posts,0);medical.page.off('request',count);
   assert.equal((await call(medical.page,'/scheduling-mvp/medical/appointments/'+id)).body.appointment.estado_cita,'EN_ESPERA');
  });
  await step('Médico vinculado inicia atención y dispone de Finalizar atención y Reevaluación',async()=>{
   const wait=medical.page.waitForResponse(r=>r.url().endsWith('/care/start')&&r.request().method()==='POST');
   await medical.page.locator('[data-medical-action=start]').click();assert.equal((await wait).status(),200);
   await medical.page.locator('[data-medical-action=finish]').waitFor();assert.equal(await medical.page.locator('[data-medical-action=indicate]').isVisible(),true);
   await shot(medical.page,'05-inicio-medico-desktop');
  });
  await step('Indicación médica, listo manual por Admisión y retirada médica conservan decisiones e historial separados',async()=>{
   let wait=medical.page.waitForResponse(r=>r.url().endsWith('/care/indicate')&&r.request().method()==='POST');
   await medical.page.locator('[data-medical-action=indicate]').click();assert.equal((await wait).status(),200);
   await admission.page.reload();await admissionRow().waitFor();await admissionRow().click();
   await admission.page.getByRole('button',{name:'Marcar listo para reevaluación',exact:true}).waitFor();
   let manual=admission.page.waitForResponse(r=>r.url().endsWith('/manual-reevaluation')&&r.request().method()==='PATCH');
   await admission.page.getByRole('button',{name:'Marcar listo para reevaluación',exact:true}).click();await admission.page.locator('.swal2-confirm').click();
   assert.equal((await manual).status(),200);
   await admission.page.waitForFunction(()=>document.querySelector('[data-kpi=reevaluaciones]')?.textContent==='1');
   let state=(await call(admission.page,'/scheduling-mvp/admission/appointments/'+id)).body;
   assert.equal(state.reevaluation.pending,false);assert.equal(state.reevaluation.ready,true);assert.deepEqual(state.reevaluation.origins,['MANUAL_DIRECT']);
   assert.equal(state.appointment.estado_cita,'EN_ATENCION');assert.equal(state.appointment.tiempos.fin,null);
   await shot(admission.page,'05a-listo-manual-sin-procedimientos');
   manual=admission.page.waitForResponse(r=>r.url().endsWith('/manual-reevaluation')&&r.request().method()==='PATCH');
   await admission.page.getByRole('button',{name:'Retirar marca manual directa',exact:true}).click();await admission.page.locator('.swal2-confirm').click();assert.equal((await manual).status(),200);
   await admission.page.waitForFunction(()=>document.querySelector('[data-kpi=reevaluaciones_pendientes]')?.textContent==='1');
   await medical.page.locator('#medical-filters button[type=submit]').click();await medicalRow().waitFor();await medicalRow().click();
   await medical.page.locator('[data-medical-action=withdraw]').waitFor();
   wait=medical.page.waitForResponse(r=>r.url().endsWith('/care/withdraw')&&r.request().method()==='POST');
   await medical.page.locator('[data-medical-action=withdraw]').click();assert.equal((await wait).status(),200);
   await medical.page.locator('[data-medical-action=indicate]').waitFor();
   const detail=(await call(medical.page,'/scheduling-mvp/medical/appointments/'+id)).body;
   assert.equal(detail.appointment.reevaluation.pending,false);assert.equal(detail.appointment.estado_cita,'EN_ATENCION');
   assert.ok(detail.events.some(e=>e.type==='REEVALUACION_MEDICA_INDICADA'));assert.ok(detail.events.some(e=>e.type==='REEVALUACION_MEDICA_RETIRADA'));
   await shot(medical.page,'05b-indicacion-retirada-historial');
  });
  let originalColor,originalMoney;
  await step('Médico indica procedimientos propios y pendiente sin cambiar estado, color ni dinero',async()=>{
   await medical.page.locator('input[name=procedures][value=laboratory]').check();await medical.page.locator('input[name=procedures][value=radiography]').check();
   let wait=medical.page.waitForResponse(r=>r.url().endsWith('/procedures')&&r.request().method()==='POST');
   await medical.page.getByRole('button',{name:'Revisar procedimientos',exact:true}).click();assert.equal((await wait).status(),200);
   const before=(await call(admission.page,'/scheduling-mvp/admission/feed','POST',{fecha:'2026-10-21',site_id:null})).body.rows.find(r=>r.id===id);
   originalColor=before.presentation.fringe;originalMoney=before.economy;
   wait=medical.page.waitForResponse(r=>r.url().endsWith('/care/indicate')&&r.request().method()==='POST');
   await medical.page.locator('[data-medical-action=indicate]').click();assert.equal((await wait).status(),200);
   await medical.page.locator('#medical-detail').getByText('PENDIENTE DE REEVALUACIÓN',{exact:true}).waitFor();
   const after=(await call(admission.page,'/scheduling-mvp/admission/feed','POST',{fecha:'2026-10-21',site_id:null})).body.rows.find(r=>r.id===id);
   assert.equal(after.estado_cita,'EN_ATENCION');assert.equal(after.reevaluation.pending,true);assert.equal(after.reevaluation.ready,false);
   assert.deepEqual(after.presentation.fringe,originalColor);assert.deepEqual(after.economy,originalMoney);assert.equal(after.tiempos.salida,null);
   await shot(medical.page,'06-pendiente-medico-desktop');
   await medical.page.setViewportSize({width:390,height:844});await shot(medical.page,'07-pendiente-medico-mobile');
   assert.ok(await medical.page.evaluate(()=>document.documentElement.scrollWidth)<=391);
   await medical.page.setViewportSize({width:1440,height:1000});
  });
  await step('Pendiente bloquea Finalizar en la pantalla y en el endpoint sin alterar cita ni dinero',async()=>{
   assert.equal(await medical.page.locator('[data-medical-action=finish]').count(),0);
   await medical.page.getByText(/No se puede finalizar hasta/).waitFor();
   const before=(await call(medical.page,'/scheduling-mvp/medical/appointments/'+id)).body;
   const result=await call(medical.page,'/scheduling-mvp/medical/appointments/'+id+'/care/finish','POST',{
    expected_state:before.appointment.estado_cita,revision:before.revision,confirmed:true,reason:'Intento FICTICIO pendiente',request_key:require('node:crypto').randomUUID()});
   assert.equal(result.status,422);
   const after=(await call(medical.page,'/scheduling-mvp/medical/appointments/'+id)).body;
   assert.equal(after.revision,before.revision);assert.deepEqual(after.appointment,before.appointment);assert.deepEqual(after.events,before.events);
   assert.deepEqual(after.procedures,before.procedures);assert.equal(after.appointment.reevaluation.pending,true);
   await shot(medical.page,'06a-pendiente-sin-finalizar');
  });
  await step('Admisión distingue pendientes/listos y conserva el color principal en escritorio y móvil',async()=>{
   await admission.page.reload();await admissionRow().waitFor();
   await admission.page.locator('#admission-only-pending-reviews').check();await admissionRow().waitFor();
   const filtered=(await call(admission.page,'/scheduling-mvp/admission/feed','POST',{fecha:'2026-10-21',site_id:null,only_pending_reevaluations:true})).body;
   assert.equal(filtered.total,1);assert.equal(filtered.kpis.reevaluaciones,0);
   await shot(admission.page,'08-pendiente-admision-desktop');
   await admission.page.setViewportSize({width:390,height:844});await admissionRow().click();
   await admission.page.getByLabel('Laboratorio realizado',{exact:true}).waitFor();
   assert.equal(await admission.page.getByRole('button',{name:'Iniciar atención',exact:true}).count(),0);
   assert.equal(await admission.page.getByRole('button',{name:'Finalizar atención',exact:true}).count(),0);
   await shot(admission.page,'09-pendiente-admision-mobile');
   assert.ok(await admission.page.evaluate(()=>document.documentElement.scrollWidth)<=391);
   await admission.page.locator('#admission-back-list').click();await admission.page.setViewportSize({width:1440,height:1000});
   await admission.page.locator('#admission-only-pending-reviews').uncheck();await admissionRow().waitFor();await admissionRow().click();
  });
  await step('Admisión completa procedimientos; listo reemplaza pendiente con contadores separados',async()=>{
   for(const label of ['Laboratorio realizado','Radiografía realizado']){
    const input=admission.page.getByLabel(label,{exact:true});await input.waitFor();
    const wait=admission.page.waitForResponse(r=>r.url().endsWith('/procedures/completion')&&r.request().method()==='POST');
    const [response]=await Promise.all([wait,input.click()]);assert.equal(response.status(),200);
    await admission.page.waitForFunction(label=>Array.from(document.querySelectorAll('[data-procedure-completed]')).some(e=>e.getAttribute('aria-label')===label&&e.checked),label);
   }
   await admission.page.waitForFunction(()=>document.querySelector('[data-kpi=reevaluaciones_pendientes]')?.textContent==='0'&&document.querySelector('[data-kpi=reevaluaciones]')?.textContent==='1');
   const ready=(await call(admission.page,'/scheduling-mvp/admission/feed','POST',{fecha:'2026-10-21',site_id:null})).body.rows.find(r=>r.id===id);
   assert.equal(ready.reevaluation.pending,false);assert.equal(ready.reevaluation.ready,true);assert.deepEqual(ready.economy,originalMoney);
   await shot(admission.page,'10-listo-admision-desktop');
   await admission.page.locator('#admission-only-reviews').check();await admissionRow().waitFor();assert.equal(await admission.page.locator('#admission-only-pending-reviews').isChecked(),false);
   await admission.page.setViewportSize({width:390,height:844});await shot(admission.page,'11-listo-admision-mobile');await admission.page.setViewportSize({width:1440,height:1000});
  });
  await step('Médico continúa la misma cita y finaliza sin salida física ni cobros nuevos',async()=>{
   await medical.page.locator('#medical-filters button[type=submit]').click();await medicalRow().waitFor();await medicalRow().click();
   await medical.page.locator('#medical-detail').getByText('Listo para reevaluación',{exact:true}).waitFor();
   const wait=medical.page.waitForResponse(r=>r.url().endsWith('/care/finish')&&r.request().method()==='POST');
   await medical.page.locator('[data-medical-action=finish]').click();assert.equal((await wait).status(),200);
   await medical.page.waitForFunction(()=>document.querySelector('#medical-detail')?.textContent.includes('ATENCION MEDICA FINALIZADA'));
   await shot(medical.page,'12-finalizacion-medica');
   const d=(await call(admission.page,'/scheduling-mvp/admission/appointments/'+id)).body;
   assert.equal(d.appointment.estado_cita,'ATENDIDO');assert.ok(d.appointment.tiempos.fin);assert.equal(d.appointment.tiempos.salida,null);assert.deepEqual(d.appointment.economy,originalMoney);
  });
  await step('Otro médico y cuentas sin vínculo reciben403; Admisión no elude el control',async()=>{
   const wrong=await session(fixture.wrong_email);
   assert.equal((await call(wrong.page,'/scheduling-mvp/medical/appointments/'+id)).status,403);
   const unlinked=await session(fixture.unlinked_email);const denied=await unlinked.page.goto(base+'/scheduling-mvp/medical/');assert.equal(denied.status(),403);
   await shot(unlinked.page,'13-medico-sin-vinculo-403');
   assert.equal((await call(admission.page,'/scheduling-mvp/admission/appointments/'+id+'/care','POST',{state:'ATENDIDO',expected_state:'EN_ATENCION',request_key:crypto.randomUUID()})).status,403);
   await wrong.context.close();await unlinked.context.close();
  });
  await step('Revocación por administrador invalida solo la cuenta médica y conserva el historial',async()=>{
   await admin.page.goto(base+'/admin/user/'+fixture.existing_id+'/doctor-link');
   await admin.page.locator('[name=doctor_id]').selectOption('');await admin.page.locator('[name=reason]').fill('Revocación FICTICIA de acceso médico para aceptación');
   await admin.page.locator('[name=identity_confirmed]').check();
   const wait=admin.page.waitForResponse(r=>r.url().endsWith('/doctor-link')&&r.request().method()==='POST');
   await admin.page.getByRole('button',{name:'Revisar vinculación',exact:true}).click();assert.equal((await wait).status(),200);
   await admin.page.waitForFunction(()=>document.querySelector('#user-doctor-link')?.dataset.current==='');
   await shot(admin.page,'14-revocacion-historial');
   assert.equal((await call(medical.page,'/scheduling-mvp/medical/appointments/'+id)).status,401);
   assert.equal((await admin.page.goto(base+'/admin/user/index')).status(),200);
   assert.equal((await call(admission.page,'/scheduling-mvp/admission/appointments/'+id)).status,200);
  });
  await step('Base ficticia conserva una cita, un pago real y autoría médica tras retirar el vínculo',async()=>{
   const php=process.env.MEDICAL_QA_PHP;if(!php)throw new Error('Set MEDICAL_QA_PHP to the local compatible PHP binary.');
   const state=JSON.parse(execFileSync(php,[path.join(root,'tests/Support/medical-browser-inspect.php')],{cwd:root,encoding:'utf8'}));
   assert.equal(state.users,state.initial_users);assert.equal(state.appointments,1);assert.equal(state.payments,1);assert.equal(state.paid,'60.00');assert.equal(state.price,'120.00');assert.equal(state.balance,'60.00');
   assert.equal(state.doctor_id,null);assert.deepEqual(state.role_names,['MEDICO']);assert.equal(state.state,'ATENDIDO');assert.equal(state.departure,null);assert.equal(state.link_events,2);
   assert.equal(state.medical_events.length,5);assert.ok(state.medical_events.every(e=>e.actor_user_id===fixture.existing_id));
   assert.equal(state.procedures.length,2);assert.ok(state.procedures.every(p=>p.requested_by_user_id===fixture.existing_id&&p.verified_by_user_id===fixture.actor_id&&p.completed_at));
   fs.writeFileSync(path.join(out,'database-invariants.json'),JSON.stringify(state,null,2));
  });
  assert.deepEqual(report.errors,[]);save();
 }catch(e){report.failure=redact(e.message);save();throw e;}
 finally{await browser.close();}
})().catch(e=>{console.error(redact(e.message));process.exitCode=1;});
