const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require(path.join(process.env.USERPROFILE,'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'));
const root=path.resolve(__dirname,'../..'),out=path.join(root,'storage/app/qa-v2');
const read=name=>JSON.parse(fs.readFileSync(path.join(out,name),'utf8').replace(/^\uFEFF/,''));
const fixture=read('browser-fixtures.json'),secret=read('browser-private.json'),base='http://127.0.0.1:8092';
(async()=>{
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true,args:['--disable-background-networking','--host-resolver-rules=MAP * 0.0.0.0, EXCLUDE 127.0.0.1, EXCLUDE localhost']});
 const report={browser:browser.version(),cases:[],errors:[],started:new Date().toISOString()};
 const save=()=>fs.writeFileSync(path.join(out,'browser-results.json'),JSON.stringify(report,null,2));
 let current;
 async function step(name,page,work){current=page;try{await work();report.cases.push({name,result:'PASS'});console.log('PASS '+name);save();}catch(error){report.cases.push({name,result:'FAIL',message:String(error.message).split(secret.password).join('[REDACTED]')});await page.screenshot({path:path.join(out,'browser-failure.png'),fullPage:true}).catch(()=>{});fs.writeFileSync(path.join(out,'browser-failure.txt'),await page.locator('body').innerText());save();throw error;}}
 async function account(key){const context=await browser.newContext({viewport:{width:1440,height:1000},timezoneId:'America/Lima'});
  await context.route('**/*',r=>['127.0.0.1','localhost'].includes(new URL(r.request().url()).hostname)?r.continue():r.abort());
  const p=await context.newPage();p.setDefaultTimeout(20000);p.on('pageerror',e=>report.errors.push(e.message));
  await p.goto(base+'/');await p.locator('[name=email]').fill(fixture.users[key].email);await p.locator('[name=password]').fill(secret.password);
  await Promise.all([p.waitForURL('**/dashboard'),p.getByRole('button',{name:'Iniciar sesión',exact:true}).click()]);return p;}
 async function direct(p,url,method='GET',data,csrf=true){return p.evaluate(async({url,method,data,csrf})=>{
  const r=await fetch(url,{method,headers:{Accept:'application/json','Content-Type':'application/json',...(csrf?{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}:{})},...(data?{body:JSON.stringify(data)}:{})});
  return {status:r.status,body:await r.json().catch(()=>({html:true}))};},{url,method,data,csrf});}
 const detailUrl=id=>'/scheduling-mvp/admission/appointments/'+id;
 const payload=(version,extra)=>({request_key:crypto.randomUUID(),expected_version:version,...extra});
 async function detail(page,id){await page.goto(base+'/scheduling-mvp/admission?appointment_id='+id);await page.locator('#admission-detail h4').first().waitFor();await page.waitForFunction(id=>document.querySelector('#admission-detail')?.textContent.includes('CITA ')&&document.querySelector('tr.is-selected')?.dataset.appointmentId===String(id),id);}
 async function change(page,id,action,extra){const d=await direct(page,detailUrl(id));return direct(page,detailUrl(id)+'/procedures/'+action,'POST',payload(d.body.procedures.version,extra));}
 try{
  const admin=await account('admin'),admission=await account('admission'),commercial=await account('commercial');
  const id=fixture.appointments.manual,auto=fixture.appointments.auto,reverse=fixture.appointments.reversal;
  await step('ADMINISTRADOR selecciona exactamente dos procedimientos mediante confirmación explícita',admin,async()=>{
   await detail(admin,id);assert.equal(await admin.locator('[data-procedure-selection]').count(),7);
   await admin.locator('[data-procedure-selection=radiography]').check();await admin.locator('[data-procedure-selection=laboratory]').check();
   assert.equal((await direct(admin,detailUrl(id))).body.procedures.items.length,0);
   const wait=admin.waitForResponse(r=>r.url().endsWith('/'+id+'/procedures/selection'));await admin.getByRole('button',{name:'Confirmar selección',exact:true}).click();assert.equal((await wait).status(),200);
   await admin.getByLabel('Radiografía realizado',{exact:true}).waitFor();
   assert.deepEqual((await direct(admin,detailUrl(id))).body.procedures.items.map(p=>p.key),['radiography','laboratory']);
   await admin.evaluate(()=>window.scrollTo(0,0));await admin.screenshot({path:path.join(out,'admin-procedures-desktop.png'),fullPage:true});
   await admin.setViewportSize({width:390,height:844});await admin.evaluate(()=>window.scrollTo(0,0));await admin.screenshot({path:path.join(out,'admin-procedures-mobile.png'),fullPage:true});assert.ok((await admin.evaluate(()=>document.documentElement.scrollWidth))<=391);await admin.setViewportSize({width:1440,height:1000});
  });
  await step('ADMISION solo ve lo solicitado, registra realizado y envía manualmente con motivo',admission,async()=>{
   await detail(admission,id);assert.equal(await admission.locator('[data-procedure-selection]').count(),0);
   const wait=admission.waitForResponse(r=>r.url().endsWith('/'+id+'/procedures/completion'));await admission.getByLabel('Radiografía realizado',{exact:true}).click();assert.equal((await wait).status(),200);
   await admission.getByRole('button',{name:'Enviar a reevaluación',exact:true}).waitFor();
   assert.equal((await direct(admission,detailUrl(id))).body.procedures.review.status,'NOT_READY');
   await admission.getByRole('button',{name:'Enviar a reevaluación',exact:true}).click();await admission.locator('.swal2-textarea').fill('Resultado parcial ficticio, pendiente laboratorio');
   const sent=admission.waitForResponse(r=>r.url().endsWith('/'+id+'/procedures/send'));await admission.getByRole('button',{name:'Confirmar decisión',exact:true}).click();assert.equal((await sent).status(),200);
   await admission.getByRole('button',{name:'Retirar de reevaluación',exact:true}).waitFor();
   const result=(await direct(admission,detailUrl(id))).body.procedures;assert.equal(result.review.origin,'MANUAL');assert.equal(result.items[1].completed,false);
  });
  await step('COMERCIAL consulta pero recibe 403 en solicitudes, realizados y envío; creación heredada bloqueada',commercial,async()=>{
   await detail(commercial,id);assert.equal(await commercial.locator('#admission-detail input[type=checkbox]').count(),0);
   for(const[action,extra]of [['selection',{procedures:['mri']}],['completion',{procedure:'radiography',completed:false}],['send',{confirmed:true,reason:'QA'}]])assert.equal((await change(commercial,id,action,extra)).status,403);
   assert.equal((await direct(commercial,'/admissionist/appointment/store','POST',{})).status,403);
   assert.equal((await direct(admission,'/admissionist/appointment/store','POST',{})).status,403);
  });
  await step('Reevaluación automática se sincroniza en Agenda operativa y cola de Nicol sin recargar',commercial,async()=>{
   assert.equal((await change(admin,auto,'selection',{procedures:['radiography']})).status,200);
   await commercial.goto(base+'/scheduling-mvp/agenda?fecha='+fixture.date+'&doctor_id='+fixture.doctor_id);
   await commercial.locator('.agenda-day-entry[data-appointment-id="'+auto+'"]').first().waitFor();
   assert.equal((await change(admission,auto,'completion',{procedure:'radiography',completed:true})).status,200);
   const card=commercial.locator('.agenda-day-entry[data-appointment-id="'+auto+'"]').first();await card.getByText('Listo para reevaluación',{exact:false}).waitFor({timeout:18000});
   await admission.goto(base+'/admissionist/appointment');await admission.locator('[data-review-rows]').getByText('PACIENTE V2 AUTO FICTICIO',{exact:false}).waitFor();
   const queue=(await direct(admission,'/scheduling-mvp/admission/reevaluations')).body.rows;assert.equal(queue.length,2);assert.equal(queue[0].id,id);assert.equal(queue[1].id,auto);
   await admission.screenshot({path:path.join(out,'nicol-desktop.png'),fullPage:true});
  });
  await step('Refresco de admisión conserva checkboxes y texto sin guardar incluso después de perder foco',admin,async()=>{
   await detail(admin,id);await admin.locator('[data-procedure-selection=mri]').check();
   const editor=admin.getByLabel('Nueva observación para esta cita');await editor.fill('Borrador ficticio que debe conservarse');await admin.locator('#admission-detail h3').click();
   assert.equal((await change(admission,id,'completion',{procedure:'laboratory',completed:true})).status,200);
   await admin.waitForTimeout(6500);
   assert.equal(await admin.locator('[data-procedure-selection=mri]').isChecked(),true);assert.equal(await editor.inputValue(),'Borrador ficticio que debe conservarse');
   assert.equal((await direct(admin,detailUrl(id))).body.procedures.items.length,2);
   const stale=admin.waitForResponse(r=>r.url().endsWith('/'+id+'/procedures/selection'));await admin.getByRole('button',{name:'Confirmar selección',exact:true}).click();assert.equal((await stale).status(),409);
   assert.equal(await admin.locator('[data-procedure-selection=mri]').isChecked(),true);assert.equal(await editor.inputValue(),'Borrador ficticio que debe conservarse');
   await admin.locator('#admission-refresh').click();await admin.getByRole('button',{name:'Conservar cambios',exact:true}).click();assert.equal(await editor.inputValue(),'Borrador ficticio que debe conservarse');
   await admin.locator('#admission-refresh').click();await admin.getByRole('button',{name:'Descartar y actualizar',exact:true}).click();await admin.waitForFunction(()=>document.querySelector('[data-procedure-selection=mri]')?.checked===false);

  });
  await step('Retroceder estado requiere motivo, restaura el anterior y conserva auditoría',admission,async()=>{
   for(const[state,expected_state]of [['EN_ESPERA','PROGRAMADO'],['LLAMANDO','EN_ESPERA']])assert.equal((await direct(admission,detailUrl(reverse)+'/care','POST',{state,expected_state,request_key:crypto.randomUUID()})).status,200);
   await detail(admission,reverse);await admission.getByRole('button',{name:'Retroceder estado',exact:true}).click();await admission.locator('.swal2-textarea').fill('Corrección ficticia de clic');
   const wait=admission.waitForResponse(r=>r.url().endsWith('/'+reverse+'/care-reversal'));await admission.getByRole('button',{name:'Confirmar corrección',exact:true}).click();assert.equal((await wait).status(),200);
   assert.equal((await direct(admission,detailUrl(reverse))).body.appointment.estado_cita,'EN_ESPERA');
  });
  await step('Pago real desde reserva confirma automáticamente y oculta confirmación adicional',admission,async()=>{
   await admission.goto(base+'/scheduling-mvp/agenda?fecha='+fixture.date+'&doctor_id='+fixture.doctor_id+'&appointment_id='+fixture.pending_id);
   await admission.locator('#agenda-open-payment').click();
   await admission.locator('#agenda-op-submit-payment').waitFor({state:'visible'});await admission.locator('#agenda-op-amount').fill('30');
   const wait=admission.waitForResponse(r=>r.url().endsWith('/'+fixture.pending_id+'/payments')&&r.request().method()==='POST');await admission.locator('#agenda-op-submit-payment').click();const r=await wait,body=await r.json();assert.equal(r.status(),200,JSON.stringify(body));assert.equal(body.appointment.estado_agenda,'CONFIRMADA');assert.equal(body.economy.pago_real,'30.00');assert.equal(body.economy.saldo,'70.00');
   await admission.waitForFunction(()=>document.querySelector('#agenda-op-confirm-reservation').hidden===true);
  });
  await step('CSRF real rechaza escrituras sin token y páginas sensibles excluyen Clarity',commercial,async()=>{
   assert.equal((await commercial.context().request.post(base+detailUrl(id)+'/procedures/completion',{headers:{Accept:'application/json'},data:payload(1,{procedure:'radiography',completed:false})})).status(),419);
   for(const url of ['/scheduling-mvp/agenda','/scheduling-mvp/admission','/admissionist/appointment']){await commercial.goto(base+url);assert.equal(await commercial.locator('script[src*="clarity.ms"]').count(),0);assert.equal((await commercial.content()).includes('www.clarity.ms/tag/'),false);}
  });
  await step('Escritorio y móvil 390×844 muestran procedimientos, botones y cola legibles',admission,async()=>{
   await detail(admission,auto);await admission.screenshot({path:path.join(out,'admission-desktop.png'),fullPage:true});
   await admission.setViewportSize({width:390,height:844});await detail(admission,auto);
   const geometry=await admission.evaluate(()=>({width:innerWidth,scroll:document.documentElement.scrollWidth}));assert.ok(geometry.scroll<=391,JSON.stringify(geometry));
   assert.ok(await admission.getByLabel('Radiografía realizado',{exact:true}).isVisible());await admission.screenshot({path:path.join(out,'admission-mobile.png'),fullPage:true});
   await commercial.setViewportSize({width:390,height:844});await commercial.goto(base+'/scheduling-mvp/agenda?fecha='+fixture.date+'&doctor_id='+fixture.doctor_id);
   await commercial.locator('.agenda-day-entry[data-appointment-id="'+auto+'"]').first().waitFor();assert.ok((await commercial.evaluate(()=>document.documentElement.scrollWidth))<=391);await commercial.screenshot({path:path.join(out,'agenda-mobile.png'),fullPage:true});
  });
  assert.deepEqual(report.errors,[]);report.finished=new Date().toISOString();save();
 }catch(error){console.error(String(error.message).split(secret.password).join('[REDACTED]'));process.exitCode=1;}finally{save();await browser.close();}
})();
