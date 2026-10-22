const fs=require('fs'),path=require('path'),assert=require('node:assert/strict');
const {chromium}=require(path.join(process.env.USERPROFILE,'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'));
const root=path.resolve(__dirname,'../..'),dir=path.join(root,'storage/app/qa-reschedule-visual');
const f=JSON.parse(fs.readFileSync(path.join(dir,'recovery-extra-fixture.json'))),secret=JSON.parse(fs.readFileSync(path.join(dir,'browser-secret.json')));
const origin='http://127.0.0.1:8107',output=path.join(root,'docs/recuperacion-cinco/qa/extra'),checks=[],errors=[];
fs.mkdirSync(output,{recursive:true});
(async()=>{const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
try{
 async function session(email){const ctx=await browser.newContext({viewport:{width:1440,height:1000}});await ctx.route('**/*',r=>new URL(r.request().url()).origin===origin?r.continue():r.abort());
  const p=await ctx.newPage();p.on('pageerror',e=>errors.push(e.message));await p.goto(origin);await p.locator('[name=email]').fill(email);await p.locator('[name=password]').fill(secret.password);
  await Promise.all([p.waitForURL(u=>u.pathname!=='/'),p.getByRole('button',{name:'Iniciar sesión',exact:true}).click()]);return p;}
 async function admission(p,id,date='2026-10-21'){await p.goto(origin+`/scheduling-mvp/admission?fecha=${date}&doctor_id=${f.doctor_id}&appointment_id=${id}`);await p.locator(`tr[data-appointment-id="${id}"]`).waitFor();await p.locator(`tr[data-appointment-id="${id}"]`).click();await p.locator('#admission-detail .admission-consultation-reason').waitFor();}
 async function json(p,url,method='GET',body){return p.evaluate(async({url,method,body})=>{const r=await fetch(url,{method,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},...(body?{body:JSON.stringify(body)}:{})});return {status:r.status,body:await r.json()};},{url,method,body});}
 const commercial=await session(f.commercial_email);await commercial.goto(origin+'/scheduling-mvp/admission');
 const hidden=await json(commercial,`/scheduling-mvp/admission/appointments/${f.unpaid_id}`);assert.ok([200,404].includes(hidden.status));
 const staff=await session(secret.email);await admission(staff,f.unpaid_id);const button=staff.getByRole('button',{name:'Confirmar sin adelanto',exact:true});
 if(hidden.status===404){
 let sends=0;staff.on('request',r=>{if(r.url().endsWith('/confirm-without-deposit'))sends++;});
 await button.click();await staff.locator('.swal2-textarea').fill('EXCEPCION FICTICIA REVISADA');await staff.locator('.swal2-cancel').click();assert.equal(sends,0);
 const confirmed=staff.waitForResponse(r=>r.url().endsWith('/confirm-without-deposit')&&r.request().method()==='POST');
 await button.click();await staff.locator('.swal2-textarea').fill('EXCEPCION FICTICIA REVISADA');await staff.locator('.swal2-confirm').click();assert.equal((await confirmed).status(),200);
 }else checks.push('Reanudación de cita ficticia confirmada en ejecución anterior; no repite la autorización');
 await staff.locator(`tr[data-appointment-id="${f.unpaid_id}"]`).filter({hasText:'CONFIRMADA · SIN ADELANTO'}).waitFor();
 const row=staff.locator(`tr[data-appointment-id="${f.unpaid_id}"]`);assert.equal(await row.evaluate(r=>getComputedStyle(r).backgroundColor),'rgb(255, 242, 166)');
 assert.ok((await row.locator('td').evaluateAll(cells=>cells.map(c=>getComputedStyle(c).backgroundColor))).every(c=>c==='rgb(255, 242, 166)'));
 await staff.screenshot({path:path.join(output,'01-sin-adelanto.png'),fullPage:true});
 const visible=await json(commercial,`/scheduling-mvp/admission/appointments/${f.unpaid_id}`);assert.equal(visible.status,200);assert.equal(visible.body.appointment.economy.pagado,'0.00');assert.equal(visible.body.appointment.economy.saldo,'120.00');
 assert.equal((await json(commercial,`/scheduling-mvp/admission/appointments/${f.private_id}`)).status,404);checks.push('Confirmación explícita ADMISION, cancelar no envía, amarillo en toda fila, pago cero/saldo120 y otra reserva privada oculta');
 const admin=await session(f.medical_email);
 const prior=await json(admin,`/scheduling-mvp/admission/appointments/${f.paid_id}`);
 if(prior.body.appointment.fecha==='2026-10-21'){
 await admin.goto(origin+`/scheduling-mvp/agenda?fecha=2026-10-21&doctor_id=${f.doctor_id}&appointment_id=${f.paid_id}`);
 await admin.locator('#agenda-manage-selected').waitFor();await admin.locator('#agenda-manage-selected').click();
 const dialog=admin.locator('#agenda-manage-dialog');await dialog.getByRole('button',{name:'Modificar economía de la cita',exact:true}).click();
 await dialog.getByLabel('Tipo de modificación económica',{exact:true}).selectOption('DISCOUNT');
 await dialog.getByLabel('Tipo de descuento',{exact:true}).selectOption('FIXED');await dialog.getByLabel('Valor del descuento',{exact:true}).fill('20');
 await dialog.getByLabel('Fundamento del descuento',{exact:true}).fill('BENEFICIO FICTICIO');await dialog.getByLabel('Motivo del cambio económico',{exact:true}).fill('CORRECCION ADMINISTRATIVA FICTICIA');
 await dialog.getByRole('button',{name:'Revisar cambios',exact:true}).click();await dialog.getByRole('button',{name:'Confirmar y guardar cambio',exact:true}).waitFor({state:'visible'});
 await admin.screenshot({path:path.join(output,'02-revision-economica.png'),fullPage:true});
 const saved=admin.waitForResponse(r=>r.url().endsWith('/economic-edit')&&r.request().method()==='PUT');
 await dialog.getByRole('button',{name:'Confirmar y guardar cambio',exact:true}).click();await admin.locator('.swal2-confirm').click();assert.equal((await saved).status(),200);
 await dialog.locator('.agenda-manage-status').filter({hasText:'Cambio económico guardado'}).waitFor();
 const money=await json(admin,`/scheduling-mvp/admission/appointments/${f.paid_id}`);assert.equal(money.body.appointment.economy.precio,'100.00');assert.equal(money.body.appointment.economy.pagado,'60.00');assert.equal(money.body.appointment.economy.saldo,'40.00');
 await dialog.locator('summary').filter({hasText:'Auditoría económica'}).click();await admin.screenshot({path:path.join(output,'03-auditoria-economica.png'),fullPage:true});checks.push('ADMINISTRADOR revisa y guarda descuento después de pago: mismo servicio, pago60 intacto, precio100, saldo40 y auditoría visible');
 await dialog.getByRole('button',{name:'Reprogramar',exact:true}).click();await dialog.getByLabel('Nueva fecha de la cita',{exact:true}).fill('2026-10-24');await dialog.getByLabel('Nueva hora de la cita',{exact:true}).fill('12:00');await dialog.getByLabel('Motivo de reprogramación',{exact:true}).fill('REPROGRAMACION FICTICIA REVISADA');
 const moved=admin.waitForResponse(r=>r.url().endsWith('/reschedule')&&r.request().method()==='PATCH');await dialog.getByRole('button',{name:'Reprogramar cita',exact:true}).click();await admin.locator('.swal2-confirm').click();assert.equal((await moved).status(),200);
 }else {assert.equal(prior.body.appointment.economy.precio,'100.00');assert.equal(prior.body.appointment.economy.pagado,'60.00');assert.equal(prior.body.appointment.economy.saldo,'40.00');checks.push('Descuento administrativo y reprogramación ya guardados por UI en ejecución anterior: verificados sin repetir escrituras');}
 await admin.goto(origin+`/scheduling-mvp/admission?fecha=2026-10-21&doctor_id=${f.doctor_id}`);
 await admin.locator('#admission-reschedule-panel summary').click();
 const history=admin.locator('.admission-reschedule-reference').filter({hasText:'12:40'});await history.waitFor();await admin.screenshot({path:path.join(output,'04-horario-original.png'),fullPage:true});
 assert.equal(await admin.locator(`tr[data-appointment-id="${f.paid_id}"]`).count(),0);await history.getByRole('link',{name:'Ver cita reprogramada'}).click();await admin.locator(`tr[data-appointment-id="${f.paid_id}"]`).waitFor();
 assert.ok(admin.url().includes('2026-10-24'));await admin.screenshot({path:path.join(output,'05-cita-vigente.png'),fullPage:true});checks.push('Reprogramación desde editor existente: referencia original, enlace a misma cita vigente en nueva fecha e importes conservados');
 const feed=await json(admin,'/scheduling-mvp/agenda/feed?vista=dia&fecha=2026-10-21&doctor_id[]='+f.doctor_id);
 assert.equal(feed.status,200);assert.equal(feed.body.reprogramaciones.items.find(x=>x.appointment_id===f.paid_id).occupies_slot,false);
 assert.ok(feed.body.eventos.some(e=>e.extendedProps?.tipo_contexto==='slot_libre'&&e.extendedProps?.hora_inicio==='12:40'));checks.push('Agenda Operativa muestra origen histórico sin ocupar el intervalo anterior');
 await admin.goto(origin+`/admin/user/edit/${f.actor_id}`);await admin.locator('#user-doctor-link').waitFor();assert.equal(await admin.locator('#medical-link-doctor').count(),1);await admin.screenshot({path:path.join(output,'06-vinculacion-en-editor.png'),fullPage:true});checks.push('Vinculación por nombre integrada al editor de usuario existente');
 assert.deepEqual(errors,[]);fs.writeFileSync(path.join(output,'browser-results.json'),JSON.stringify({result:'PASS',checks,errors},null,2));console.log(JSON.stringify({result:'PASS',checks,errors}));
}catch(e){for(const c of browser.contexts())for(const p of c.pages())await p.screenshot({path:path.join(output,'failure-'+browser.contexts().indexOf(c)+'.png'),fullPage:true}).catch(()=>{});throw e;}finally{await browser.close();}
})().catch(e=>{fs.writeFileSync(path.join(output,'failure.txt'),e.stack);console.error(e.stack);process.exitCode=1;});
