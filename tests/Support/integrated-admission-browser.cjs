const fs=require('fs'),path=require('path'),assert=require('node:assert/strict');
const {chromium}=require(path.join(process.env.USERPROFILE,'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'));
const root=path.resolve(__dirname,'../..'),dir=path.join(root,'storage/app/qa-reschedule-visual');
const fixture=JSON.parse(fs.readFileSync(path.join(dir,'browser-fixture.json'))),secret=JSON.parse(fs.readFileSync(path.join(dir,'browser-secret.json')));
const output=path.join(root,'docs/recuperacion-cinco/qa/flow');fs.mkdirSync(output,{recursive:true});
const origin='http://127.0.0.1:8107',checks=[],errors=[];
(async()=>{
 const browser=await chromium.launch({executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
 try{
  async function session(email){const context=await browser.newContext({viewport:{width:1440,height:1000}});
   await context.route('**/*',route=>new URL(route.request().url()).origin===origin?route.continue():route.abort());
   const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
   await page.goto(origin);await page.locator('[name=email]').fill(email);await page.locator('[name=password]').fill(secret.password);
   await Promise.all([page.waitForURL(url=>url.pathname!=='/'),page.getByRole('button',{name:'Iniciar sesión',exact:true}).click()]);
   await page.goto(origin+'/scheduling-mvp/admission');await page.locator('tr[data-appointment-id="'+fixture.appointment_id+'"]').waitFor();
   return page;
  }
  async function open(page){const row=page.locator('tr[data-appointment-id="'+fixture.appointment_id+'"]');await row.click();await page.locator('#admission-detail .admission-consultation-reason').waitFor();}
  async function refresh(page){await page.getByRole('button',{name:'Actualizar',exact:true}).click();await page.locator('tr[data-appointment-id="'+fixture.appointment_id+'"]').waitFor();await open(page);}
  async function clinical(page,action,reason){const response=page.waitForResponse(r=>r.url().includes('/medical-care/'+action)&&r.request().method()==='POST');
   await page.locator('[data-medical-action="'+action+'"]').click();await page.locator('.swal2-popup').waitFor();if(reason)await page.locator('.swal2-textarea').fill(reason);await page.locator('.swal2-confirm').click();assert.equal((await response).status(),200);if(action==='start')await page.locator('[data-medical-action=indicate]').waitFor();else if(action==='indicate')await page.locator('[data-medical-action=withdraw]').waitFor();else if(action==='finish')await page.locator('.admission-journey time').nth(3).filter({hasNotText:'—'}).waitFor();}
  const commercial=await session(fixture.commercial_email);await open(commercial);
  assert.equal(await commercial.locator('[data-medical-action]').count(),0);
  await commercial.screenshot({path:path.join(output,'01-antes-llegada-desktop.png'),fullPage:true});checks.push('COMERCIAL no recibe botones clínicos');
  const arrival=commercial.waitForResponse(r=>r.url().endsWith('/arrival')&&r.request().method()==='POST');
  await commercial.getByRole('button',{name:'Registrar llegada',exact:true}).click();await commercial.locator('.swal2-confirm').click();assert.equal((await arrival).status(),200);
  const medical=await session(fixture.medical_email);await open(medical);assert.equal(await medical.getByText('Mi agenda médica',{exact:true}).count(),0);
  await clinical(medical,'start');assert.equal(await medical.locator('[data-medical-action=finish]').count(),1);assert.equal(await medical.locator('[data-medical-action=indicate]').count(),1);checks.push('Médico ADMINISTRADOR + MEDICO inicia desde Admisión y conserva ambos botones');
  for(const key of ['radiography','laboratory','wound_care'])await medical.locator('[data-procedure-selection="'+key+'"]').check();
  const selection=medical.waitForResponse(r=>r.url().endsWith('/medical-procedures')&&r.request().method()==='POST');await medical.getByRole('button',{name:'Confirmar selección',exact:true}).click();assert.equal((await selection).status(),200);
  await medical.locator('#admission-notice').filter({hasText:'Procedimientos y reevaluación actualizados'}).waitFor({timeout:5000}).catch(()=>{});
  await clinical(medical,'indicate','REEVALUACION FICTICIA PARA QA LOCAL');assert.equal(await medical.locator('[data-medical-action=finish]').count(),0);checks.push('Reevaluación pendiente retira Finalizar y mantiene la misma cita');
  await medical.screenshot({path:path.join(output,'02-pendiente-desktop.png'),fullPage:true});
  await refresh(commercial);
  for(const key of ['radiography','laboratory']){const r=commercial.waitForResponse(r=>r.url().endsWith('/procedures/completion')&&r.request().method()==='POST');await commercial.locator('[data-procedure-completed="'+key+'"]').click();assert.equal((await r).status(),200);await commercial.locator('#admission-notice').filter({hasText:'Procedimientos y reevaluación actualizados'}).waitFor();}
  await commercial.locator('.admission-procedure-explanation').filter({hasText:'2 de 3 procedimientos realizados'}).waitFor();checks.push('2 de 3 sigue pendiente');
  await commercial.setViewportSize({width:390,height:844});await commercial.screenshot({path:path.join(output,'03-dos-de-tres-mobile.png'),fullPage:true});
  const completed=commercial.waitForResponse(r=>r.url().endsWith('/procedures/completion')&&r.request().method()==='POST');await commercial.locator('[data-procedure-completed=wound_care]').click();assert.equal((await completed).status(),200);
  await commercial.locator('.admission-procedure-explanation').filter({hasText:'3 de 3 procedimientos realizados'}).waitFor();
  await commercial.getByRole('button',{name:'Volver a la lista',exact:true}).click();await commercial.locator('[data-review-only]').click();await commercial.locator('tr[data-appointment-id="'+fixture.appointment_id+'"]').waitFor();
  await commercial.screenshot({path:path.join(output,'04-listo-mobile.png'),fullPage:true});checks.push('3 de 3 activa Listos automáticamente y filtro en móvil');
  await refresh(medical);await clinical(medical,'finish');await medical.screenshot({path:path.join(output,'05-finalizada-desktop.png'),fullPage:true});
  await commercial.setViewportSize({width:1440,height:1000});await commercial.locator('[data-review-only]').click();await refresh(commercial);
  const departure=commercial.waitForResponse(r=>r.url().endsWith('/departure')&&r.request().method()==='POST');await commercial.getByRole('button',{name:'Registrar salida',exact:true}).click();await commercial.locator('.swal2-confirm').click();assert.equal((await departure).status(),200);
  await commercial.locator('#admission-notice').filter({hasText:'Salida registrada'}).waitFor();await commercial.screenshot({path:path.join(output,'06-salida-desktop.png'),fullPage:true});checks.push('Finalización médica separada de salida física COMERCIAL');
  assert.deepEqual(errors,[]);checks.push('Sin errores JavaScript y sin navegación a otra agenda');
  fs.writeFileSync(path.join(output,'browser-results.json'),JSON.stringify({result:'PASS',checks,errors},null,2));console.log(JSON.stringify({result:'PASS',checks,errors}));
 }catch(e){console.error(JSON.stringify({errors}));for(const context of browser.contexts())for(const page of context.pages())await page.screenshot({path:path.join(output,'debug-'+browser.contexts().indexOf(context)+'.png'),fullPage:true}).catch(()=>{});console.error(e.stack);throw e;}finally{await browser.close();}
})().catch(e=>{fs.writeFileSync(path.join(output,'browser-failure.txt'),e.stack);console.error(e.stack);process.exitCode=1;});
