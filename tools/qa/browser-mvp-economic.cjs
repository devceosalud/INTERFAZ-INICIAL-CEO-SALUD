const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require(path.join(process.env.USERPROFILE,'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'));
const root=path.resolve(__dirname,'../..'),out=path.join(root,'storage/app/qa-reschedule-visual'),base='http://127.0.0.1:8099';
const fixture=JSON.parse(fs.readFileSync(path.join(out,'browser-fixture.json'),'utf8')),secret=JSON.parse(fs.readFileSync(path.join(out,'browser-secret.json'),'utf8'));
(async()=>{
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true,args:['--disable-background-networking','--host-resolver-rules=MAP * 0.0.0.0, EXCLUDE 127.0.0.1, EXCLUDE localhost']});
 const report={browser:browser.version(),cases:[],errors:[]};const save=()=>fs.writeFileSync(path.join(out,'economic-browser-results.json'),JSON.stringify(report,null,2));
 async function account(email){const c=await browser.newContext({viewport:{width:1440,height:1000},timezoneId:'America/Lima'});await c.route('**/*',r=>new URL(r.request().url()).hostname==='127.0.0.1'?r.continue():r.abort());const p=await c.newPage();p.setDefaultTimeout(20000);p.on('pageerror',e=>report.errors.push(e.message));await p.goto(base+'/');await p.locator('[name=email]').fill(email);await p.locator('[name=password]').fill(secret.password);await Promise.all([p.waitForURL('**/dashboard'),p.getByRole('button',{name:'Iniciar sesión',exact:true}).click()]);return p;}
 async function direct(p,url,method='GET',body){return p.evaluate(async({url,method,body})=>{const r=await fetch(url,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},...(body?{body:JSON.stringify(body)}:{})});return {status:r.status,body:await r.json()};},{url,method,body});}
 async function step(name,p,fn){try{await fn();report.cases.push({name,result:'PASS'});console.log('PASS '+name);save();}catch(e){report.cases.push({name,result:'FAIL',message:String(e.message).replaceAll(secret.password,'[REDACTED]')});await p.screenshot({path:path.join(out,'economic-failure.png'),fullPage:true});save();throw e;}}
 const url='/scheduling-mvp/agenda/appointments/'+fixture.appointment_id+'/economic-edit';
 const agenda=base+'/scheduling-mvp/agenda?fecha=2026-10-21&doctor_id='+fixture.doctor_id;
 const dialog=p=>p.locator('#agenda-manage-dialog');
 async function open(p,action='economy'){if(!await dialog(p).isVisible()){await p.goto(agenda);await p.locator('.agenda-day-entry[data-appointment-id="'+fixture.appointment_id+'"]').first().dblclick();await dialog(p).waitFor();}await dialog(p).locator('[data-action='+action+']').click();await dialog(p).getByRole('button',{name:'Revisar cambios',exact:true}).waitFor();}
 async function review(p,reason){await dialog(p).getByLabel('Motivo del cambio económico',{exact:true}).fill(reason);const wait=p.waitForResponse(r=>r.url().endsWith('/economic-edit/review'));await dialog(p).getByRole('button',{name:'Revisar cambios',exact:true}).click();assert.equal((await wait).status(),200);await dialog(p).getByRole('button',{name:'Confirmar y guardar cambio',exact:true}).waitFor({state:'visible'});assert.equal(await dialog(p).getByRole('button',{name:'Confirmar y guardar cambio',exact:true}).isEnabled(),true);}
 async function confirmSave(p){await dialog(p).getByRole('button',{name:'Confirmar y guardar cambio',exact:true}).click();await dialog(p).locator('.swal2-confirm').waitFor();const wait=p.waitForResponse(r=>r.request().method()==='PUT'&&r.url().endsWith('/economic-edit'));await dialog(p).locator('.swal2-confirm').click();const response=await wait;assert.equal(response.status(),200,await response.text());await p.waitForFunction(()=>document.querySelector('#agenda-manage-dialog .agenda-manage-status')?.textContent.includes('guardado y auditado'));}
 async function snapshot(p,expected){const r=await direct(p,url);assert.equal(r.status,200);for(const[k,v]of Object.entries(expected))assert.equal(r.body[k],v,k);assert.equal(r.body.payments.length,1);return r.body;}
 async function shot(p,name){await p.screenshot({path:path.join(out,name),fullPage:p.viewportSize().width>500});}
 try{
  const commercial=await account(secret.email),admin=await account(fixture.admin_email);
  await step('Sin permiso directo: Comercial no corrige aunque puede registrar adelantos',commercial,async()=>{
   await commercial.goto(agenda);await commercial.locator('.agenda-day-entry[data-appointment-id="'+fixture.appointment_id+'"]').first().dblclick();await dialog(commercial).waitFor();
   assert.equal(await dialog(commercial).locator('[data-action=economy]').isEnabled(),false);assert.equal(await dialog(commercial).locator('[data-action=payment]').isEnabled(),false);
   assert.equal((await direct(commercial,url)).status,403);await shot(commercial,'economic-01-sin-permisos.png');await dialog(commercial).getByRole('button',{name:'Cerrar',exact:true}).click();
  });
  await step('Roles → Actualizar Permiso habilita edición directa para Comercial sin aprobación separada',admin,async()=>{
   await admin.goto(base+'/admin/role/edit/'+fixture.commercial_role_id);admin.on('dialog',d=>d.accept());
   await admin.getByLabel('Corregir adelantos registrados',{exact:false}).check();await admin.getByLabel('Modificar economía de citas existentes',{exact:false}).check();
   await shot(admin,'economic-02-permisos-rol.png');const wait=admin.waitForResponse(r=>r.url().includes('/admin/role/update/')&&r.request().method()==='POST');await admin.locator('form').getByRole('button',{name:/Actualizar|Guardar/}).click();assert.ok([200,302].includes((await wait).status()));
  });
  await step('Caso A y cancelación: 50→60 en el mismo pago; cancelar no persiste',commercial,async()=>{
   await open(commercial,'payment');await dialog(commercial).getByLabel('Importe correcto del pago',{exact:true}).fill('60');await review(commercial,'Registro FICTICIO original50; recibido realmente60');
   await dialog(commercial).locator('.agenda-manage-comparison').first().scrollIntoViewIfNeeded();await shot(commercial,'economic-03-A-revision.png');await dialog(commercial).getByRole('button',{name:'Confirmar y guardar cambio',exact:true}).click();await dialog(commercial).locator('.swal2-cancel').click();
   let s=await snapshot(commercial,{price:'120.00',paid:'50.00',balance:'70.00'});assert.equal(s.history.length,0);
   await confirmSave(commercial);s=await snapshot(commercial,{price:'120.00',paid:'60.00',balance:'60.00'});assert.equal(s.payments[0].id,fixture.payment_id);assert.equal(s.history.length,1);await shot(commercial,'economic-04-A-guardado.png');
  });
  await step('Caso B: descuento posterior20 conserva pago60 y saldo40; escritorio y móvil',commercial,async()=>{
   await open(commercial);await dialog(commercial).getByLabel('Tipo de modificación económica',{exact:true}).selectOption('DISCOUNT');await dialog(commercial).getByLabel('Tipo de descuento',{exact:true}).selectOption('FIXED');
   await dialog(commercial).getByLabel('Valor del descuento',{exact:true}).fill('20');await dialog(commercial).getByLabel('Fundamento del descuento',{exact:true}).fill('Beneficio FICTICIO autorizado');await review(commercial,'Descuento posterior FICTICIO20');await confirmSave(commercial);
   await snapshot(commercial,{price:'100.00',paid:'60.00',balance:'40.00'});await shot(commercial,'economic-05-B-desktop.png');await commercial.setViewportSize({width:390,height:844});await dialog(commercial).getByLabel('Tipo de modificación económica',{exact:true}).scrollIntoViewIfNeeded();await shot(commercial,'economic-06-B-mobile.png');
   assert.ok(await commercial.evaluate(()=>document.documentElement.scrollWidth)<=391);
  });
  await step('Retirar descuento y Caso C: recargo válido20 produce140, pago60 y saldo80',commercial,async()=>{
   await dialog(commercial).getByLabel('Tipo de modificación económica',{exact:true}).selectOption('DISCOUNT');await dialog(commercial).getByLabel('Tipo de descuento',{exact:true}).selectOption('');await review(commercial,'Retiro FICTICIO del descuento');await dialog(commercial).locator('.agenda-manage-comparison').first().scrollIntoViewIfNeeded();await shot(commercial,'economic-06b-retiro-mobile.png');await confirmSave(commercial);await snapshot(commercial,{price:'120.00',paid:'60.00',balance:'60.00'});await commercial.setViewportSize({width:1440,height:1000});
   await dialog(commercial).getByLabel('Tipo de modificación económica',{exact:true}).selectOption('SURCHARGE');await dialog(commercial).getByLabel('Recargo del mismo servicio',{exact:true}).selectOption(String(fixture.rate_id));await review(commercial,'Recargo FICTICIO válido del catálogo');await dialog(commercial).locator('.agenda-manage-comparison').first().scrollIntoViewIfNeeded();await shot(commercial,'economic-07-C-revision.png');await confirmSave(commercial);
   await snapshot(commercial,{price:'140.00',paid:'60.00',balance:'80.00'});await shot(commercial,'economic-08-C-guardado.png');
  });
  await step('Referencia bancaria y tarifa se corrigen con auditoría y sin nuevos pagos',commercial,async()=>{
   await open(commercial,'payment');await dialog(commercial).getByLabel('Dato a corregir',{exact:true}).selectOption('reference');await dialog(commercial).getByLabel('Referencia bancaria correcta',{exact:true}).fill('FAKE-MVP-CORRECTA');await review(commercial,'Rectificación FICTICIA referencia bancaria');await confirmSave(commercial);
   assert.equal((await snapshot(commercial,{paid:'60.00'})).payments[0].operation,'FAKE-MVP-CORRECTA');
   await open(commercial);await dialog(commercial).getByLabel('Tipo de modificación económica',{exact:true}).selectOption('CONTRACT');await dialog(commercial).getByLabel('Tarifa base sin recargo',{exact:true}).fill('130');await dialog(commercial).getByLabel('Recargo del mismo servicio',{exact:true}).selectOption('');await review(commercial,'Tarifa contractual FICTICIA130');await confirmSave(commercial);
   const s=await snapshot(commercial,{price:'130.00',paid:'60.00',balance:'70.00'});assert.equal(s.history.length,6);await dialog(commercial).getByText('Auditoría económica',{exact:true}).click();await shot(commercial,'economic-09-auditoria.png');
  });
  await step('Integración: reprogramación conserva el historial visual y la economía corregida',commercial,async()=>{
   await dialog(commercial).getByRole('button',{name:'Cerrar',exact:true}).click();const d=await direct(commercial,'/scheduling-mvp/agenda/appointments/'+fixture.appointment_id+'/quick-edit');
   const r=await direct(commercial,'/scheduling-mvp/agenda/appointments/'+fixture.appointment_id+'/reschedule','PATCH',{fecha_cita:'2026-10-24',hora_cita:'12:00',expected_fecha_cita:'2026-10-21',expected_hora_cita:'12:20',motivo:'Reprogramación FICTICIA después de correcciones'});assert.equal(r.status,200);
   await commercial.goto(agenda);await commercial.locator('.agenda-day-row[data-row-start="12:20"] .agenda-rescheduled-marker').waitFor();assert.match(await commercial.locator('.agenda-rescheduled-marker').first().innerText(),/liberado y disponible/);assert.equal(await commercial.locator('#agenda-total-busy').innerText(),'0');
   await shot(commercial,'economic-10-integracion-reprogramacion.png');await snapshot(commercial,{price:'130.00',paid:'60.00',balance:'70.00'});
  });
  assert.deepEqual(report.errors,[]);save();
 }finally{await browser.close();}
})().catch(e=>{console.error(String(e.message).replaceAll(secret.password,'[REDACTED]'));process.exitCode=1;});
