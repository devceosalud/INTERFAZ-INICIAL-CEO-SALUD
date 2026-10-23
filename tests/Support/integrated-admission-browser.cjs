const fs=require('fs'),path=require('path'),assert=require('node:assert/strict');
const {chromium}=require(path.join(process.env.USERPROFILE,'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'));
const root=path.resolve(__dirname,'../..'),dir=path.join(root,'storage/app/qa-reschedule-visual');
const f=JSON.parse(fs.readFileSync(path.join(dir,'integration-fixture.json'))),secret=JSON.parse(fs.readFileSync(path.join(dir,'integration-secret.json')));
const base='http://127.0.0.1:8108',out=path.join(root,'docs/recuperacion-cinco/qa/integration');fs.mkdirSync(out,{recursive:true});
const results=[],errors=[];let browser;const sessions={};
function record(name){results.push({name,result:'PASS'});}
async function api(page,url,body,method='POST'){
 return page.evaluate(async({url,body,method})=>{const r=await fetch(url,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},...(method==='GET'?{}:{body:JSON.stringify(body)})});return {status:r.status,data:await r.json()};},{url,body,method});
}
async function open(role,id){const page=sessions[role];await page.goto(`${base}/scheduling-mvp/admission?fecha=${f.date}&appointment_id=${id}&doctor_id=${f.doctor_id}`);try{await page.locator('#admission-detail .admission-care-actions').waitFor();}catch(e){throw new Error(JSON.stringify({role,id,errors,notice:await page.locator('#admission-notice').textContent(),detail:await page.locator('#admission-detail').textContent()}));}return page;}
async function detail(role,id){const result=await api(sessions[role],`/scheduling-mvp/admission/appointments/${id}`,null,'GET');assert.equal(result.status,200);return result.data;}
async function button(page,name,endpoint,reason){
 const response=page.waitForResponse(r=>r.request().method()!=='GET'&&r.url().includes(endpoint)).catch(()=>null);
 await page.getByRole('button',{name,exact:true}).click();await page.locator('.swal2-popup').waitFor();
 if(reason!==undefined)await page.locator('.swal2-textarea').fill(reason);
 await page.locator('.swal2-confirm').click();const r=await response;assert.equal(r.status(),200,await r.text());
 await page.locator('.swal2-popup').waitFor({state:'hidden'});
}
async function selectExams(id){const p=await open('medical',id);for(const key of ['radiography','laboratory','wound_care'])await p.locator(`[data-procedure-selection=${key}]`).check();
 const response=p.waitForResponse(r=>r.url().endsWith('/procedures/selection')).catch(()=>null);await p.getByRole('button',{name:'Confirmar selección',exact:true}).click();assert.equal((await response).status(),200);}
(async()=>{browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
try{
 for(const role of ['actor','medical','other','commercial','admin']){
  const context=await browser.newContext({viewport:{width:1440,height:1050}});await context.route('**/*',route=>new URL(route.request().url()).origin===base?route.continue():route.abort());
  const p=await context.newPage();p.on('pageerror',e=>errors.push({role,message:e.message}));await p.goto(base);await p.locator('[name=email]').fill(secret.accounts[role]);await p.locator('[name=password]').fill(secret.password);
  await Promise.all([p.waitForURL(u=>u.pathname!=='/'),p.getByRole('button',{name:'Iniciar sesión',exact:true}).click()]);sessions[role]=p;
  await p.goto(base+'/scheduling-mvp/admission');
 }
 // Obtain paths from the actual detail route, rather than assume a second UI.
 const id=f.cases.paid;let p=await open('commercial',id);
 const basePath=await p.locator('#admission-board').getAttribute('data-base');
 // Normalise detail helper to the existing base declared by Blade.
 detail=async(role,id)=>{const r=await api(sessions[role],basePath+'/'+id,null,'GET');assert.equal(r.status,200);return r.data;};
 const before=(await detail('commercial',id)).appointment.economy;
 let r=await api(sessions.medical,basePath+'/'+id+'/care',{state:'EN_ATENCION',expected_state:'PROGRAMADO',confirmed:true,request_key:crypto.randomUUID()});assert.equal(r.status,422);record('Médico bloqueado antes de llegada');
 r=await api(sessions.other,basePath+'/'+id+'/care',{state:'EN_ATENCION',expected_state:'PROGRAMADO',confirmed:true,request_key:crypto.randomUUID()});assert.equal(r.status,403);record('Otro médico no puede firmar');
 await button(p,'Registrar llegada','/arrival');record('Llegada COMERCIAL en interfaz');
 p=await open('medical',id);await button(p,'Iniciar atención','/care');
 p=await open('medical',id);await button(p,'Reevaluación','/physician-reevaluation/indicate');
 assert.equal((await detail('commercial',id)).medical_reevaluation.pre_pending,true);record('Indicación sin confirmación operativa');
 p=await open('actor',id);await button(p,'Confirmar reevaluación pendiente','/confirm-reevaluation');await selectExams(id);
 assert.equal((await detail('commercial',id)).procedures.completed_count,0);record('Confirmación independiente y 0/3 pendientes');
 for(const [i,key] of ['radiography','laboratory','wound_care'].entries()){
  p=await open('commercial',id);const response=p.waitForResponse(r=>r.url().endsWith('/procedures/completion')).catch(()=>null);await p.locator(`[data-procedure-completed=${key}]`).click();assert.equal((await response).status(),200);
  const d=await detail('commercial',id);assert.equal(d.procedures.completed_count,i+1);assert.equal(d.medical_reevaluation.ready,i===2);record(`${i+1}/3 comprobados por COMERCIAL`);
 }
 p=await open('commercial',id);await p.screenshot({path:path.join(out,'ready-desktop.png'),fullPage:true});
 r=await api(sessions.medical,basePath+'/'+id+'/care',{state:'ATENDIDO',expected_state:'EN_ATENCION',confirmed:true,request_key:crypto.randomUUID()});assert.equal(r.status,422);record('Listo no equivale a segunda revisión realizada');
 p=await open('medical',id);await button(p,'Iniciar reevaluación','/physician-reevaluation/resume');
 r=await api(sessions.commercial,basePath+'/'+id+'/decline-reevaluation',{expected_state:'EN_ATENCION',confirmed:true,reason:'Intento tardío ficticio',request_key:crypto.randomUUID()});assert.equal(r.status,422);record('Rechazo bloqueado tras retorno médico');
 p=await open('medical',id);await button(p,'Finalizar atención','/care');assert.equal((await detail('actor',id)).appointment.tiempos.salida,null);
 p=await open('actor',id);await button(p,'Registrar salida','/care');assert.deepEqual((await detail('actor',id)).appointment.economy,before);record('Finalización y salida separadas, economía conservada');
 // Manual 0/3, patient refusal, and ordinary visit are separate fictional appointments.
 for(const [key,mode] of [['manual','manual'],['declined','declined'],['normal','normal']]){
  const id=f.cases[key];p=await open('actor',id);await button(p,'Registrar llegada','/arrival');p=await open('medical',id);await button(p,'Iniciar atención','/care');
  if(mode!=='normal'){
   p=await open('medical',id);await button(p,'Reevaluación','/physician-reevaluation/indicate');await selectExams(id);
   if(mode==='manual'){
    p=await open('commercial',id);await button(p,'Confirmar reevaluación pendiente','/confirm-reevaluation');
    p=await open('commercial',id);await button(p,'Autorizar reevaluación manual','/manual-reevaluation','Retorno autorizado con resultados pendientes.');
    assert.equal((await detail('actor',id)).procedures.completed_count,0);p=await open('medical',id);await button(p,'Iniciar reevaluación','/physician-reevaluation/resume');
   }else{
    r=await api(sessions.commercial,basePath+'/'+id+'/decline-reevaluation',{expected_state:'EN_ATENCION',confirmed:true,reason:'',request_key:crypto.randomUUID()});assert.equal(r.status,422);
    p=await open('commercial',id);await button(p,'Paciente no desea reevaluación · Finalizar','/decline-reevaluation','Paciente no desea regresar al consultorio.');
    const d=await detail('actor',id);assert.equal(d.medical_reevaluation.declined,true);assert.equal(d.medical_reevaluation.resumed,false);assert.equal(d.procedures.completed_count,0);
   }
  }
  if(mode!=='declined'){p=await open('medical',id);await button(p,'Finalizar atención','/care');}
  p=await open('actor',id);await button(p,'Registrar salida','/care');record('Recorrido '+mode+' y salida en interfaz');
 }
 // Unpaid confirmation remains a distinct, explicitly authorised exception.
 p=await open('actor',f.cases.without_deposit);await button(p,'Confirmar sin adelanto','/confirm-without-deposit','Paciente pagará al llegar, autorización ficticia.');
 let d=await detail('commercial',f.cases.without_deposit);assert.equal(d.appointment.presentation.clave,'CONFIRMADA_SIN_ADELANTO');assert.equal(d.appointment.economy.saldo,'120.00');assert.equal(d.payments.length,0);
 r=await api(sessions.commercial,basePath+'/'+f.cases.private,null,'GET');assert.equal(r.status,404);record('Sin adelanto visible y reserva ajena sigue privada');
 p=await open('actor',f.cases.declined);await p.setViewportSize({width:390,height:844});await p.screenshot({path:path.join(out,'declined-mobile.png'),fullPage:true});
 assert.deepEqual(errors,[]);fs.writeFileSync(path.join(out,'browser-flow.json'),JSON.stringify({result:'PASS',checks:results.length,results,errors},null,2));console.log(JSON.stringify({result:'PASS',checks:results.length,errors}));
}finally{await browser.close();}})().catch(e=>{console.error(e.stack);process.exitCode=1;});
