const fs=require('fs'),path=require('path'),assert=require('node:assert/strict');
const {chromium}=require(path.join(process.env.USERPROFILE,'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'));
const root=path.resolve(__dirname,'../..'),privateDir=path.join(root,'storage/app/qa-colors'),cohort=path.join(root,'storage/app/qa-reschedule-visual');
const fixture=JSON.parse(fs.readFileSync(path.join(cohort,'colors-fixture.json'))),secret=JSON.parse(fs.readFileSync(path.join(cohort,'colors-secret.json')));
const output=process.env.CEO_CORPORATE_VISUAL_QA==='1'?path.join(root,'docs/ui-agendas/qa/colors'):path.join(root,'docs/recuperacion-cinco/qa',process.env.CEO_FINAL70_COLOR_QA==='1'?'ajustes-finales70/colors':process.env.CEO_OBS70_COLOR_QA==='1'?'observaciones70/colors':'colors');fs.mkdirSync(output,{recursive:true});
const base='http://127.0.0.1:8108',results=[],errors=[];
(async()=>{const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
try{
 for(const variant of ['fixed-current-css']){
  for(const role of (variant==='fixed-current-css'?['admin','commercial','actor','medical']:['admin'])){
   const context=await browser.newContext({viewport:{width:1440,height:1050}});
   await context.route('**/*',route=>new URL(route.request().url()).origin===base?route.continue():route.abort());
   if(variant.startsWith('merged'))await context.route('**/js/scheduling/admission-board.js?*',r=>r.fulfill({contentType:'application/javascript',body:fs.readFileSync(path.join(privateDir,'merged-admission.js'),'utf8')}));
   if(variant.includes('legacy'))await context.route('**/css/scheduling/admission-board.css?*',r=>r.fulfill({contentType:'text/css',body:fs.readFileSync(path.join(privateDir,'legacy-admission.css'),'utf8')}));
   const page=await context.newPage();page.on('pageerror',e=>errors.push({variant,role,message:e.message}));
   await page.goto(base);await page.locator('[name=email]').fill(secret.accounts[role]);await page.locator('[name=password]').fill(secret.password);
   await Promise.all([page.waitForURL(u=>u.pathname!=='/'),page.getByRole('button',{name:'Iniciar sesión',exact:true}).click()]);
   await page.goto(base+'/scheduling-mvp/admission?doctor_id='+fixture.doctor_id+'&appointment_id='+fixture.cases[0].id+'&fecha='+fixture.date);await page.locator('tr[data-appointment-id]').nth(12).waitFor();
   const payload=await page.evaluate(async ({date,doctor})=>{const r=await fetch('/scheduling-mvp/admission/feed',{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify({fecha:date,site_id:null,doctor_id:doctor})});if(!r.ok)throw new Error('Feed '+r.status);return r.json();},{date:fixture.date,doctor:fixture.doctor_id});assert.equal(payload.rows.length,13);
   for(const [viewport,size] of [['desktop',{width:1440,height:1050}],['mobile',{width:390,height:844}]]){
    await page.setViewportSize(size);
    if(viewport==='mobile'){
     const back=page.getByRole('button',{name:'Volver a la lista',exact:true});
     if(await back.isVisible())await back.click();
    }
    for(const phase of ['normal','selected']){
     await page.locator('tr[data-appointment-id]').evaluateAll((rows,selected)=>rows.forEach(r=>r.classList.toggle('is-selected',selected)),phase==='selected');
     const rendered=await page.locator('tr[data-appointment-id]').evaluateAll(rows=>rows.map(r=>({id:Number(r.dataset.appointmentId),classes:r.className,
       row:getComputedStyle(r).backgroundColor,cells:Array.from(r.cells).map(c=>getComputedStyle(c).backgroundColor)})));
     for(const c of fixture.cases){const server=payload.rows.find(r=>r.id===c.id),actual=rendered.find(r=>r.id===c.id);
      const channels=c.background.match(/[a-f0-9]{2}/gi).map(x=>parseInt(x,16)),expected='rgb('+channels.join(', ')+')';
      assert.equal(server.estado_cita,c.state);assert.equal(server.presentation.clave,c.key);assert.equal(server.presentation.fringe.color,c.background);
      const matches=actual.row===expected&&actual.cells.every(x=>x===expected);
      results.push({variant,role,viewport,phase,name:c.name,server_state:server.estado_cita,server_key:server.presentation.clave,expected:c.background,expected_rgb:expected,...actual,matches});
      if(variant!=='merged-legacy-css')assert.ok(matches,JSON.stringify(results.at(-1)));
     }
    }
    await page.locator('tr[data-appointment-id]').evaluateAll(rows=>rows.forEach(r=>r.classList.remove('is-selected')));
    if(role==='admin'&&['merged-legacy-css','fixed-current-css'].includes(variant)){
     await page.screenshot({path:path.join(output,variant+'-'+viewport+'.png'),fullPage:true});
     const paid=fixture.cases.find(c=>c.name==='PAGO_REAL');await page.locator('tr[data-appointment-id="'+paid.id+'"]').scrollIntoViewIfNeeded();
     await page.screenshot({path:path.join(output,variant+'-paid-'+viewport+'.png'),fullPage:true});
    }
    if(viewport==='desktop'&&variant==='fixed-current-css'){
     const paid=fixture.cases.find(c=>c.name==='PAGO_REAL');await page.locator('tr[data-appointment-id="'+paid.id+'"]').hover();
     const color=await page.locator('tr[data-appointment-id="'+paid.id+'"]').evaluate(r=>getComputedStyle(r).backgroundColor);assert.equal(color,'rgb(255, 242, 166)');
    }
   }
   await context.close();
  }
 }
 assert.deepEqual(errors,[]);const reproduced=0;
 const report={result:'PASS',checks:results.length,legacy_mismatches_reproduced:reproduced,errors,results};fs.writeFileSync(path.join(output,'browser-color-matrix.json'),JSON.stringify(report,null,2));
 console.log(JSON.stringify({result:'PASS',checks:results.length,legacy_mismatches_reproduced:reproduced,errors}));
}finally{await browser.close();}})().catch(e=>{console.error(e.stack);process.exitCode=1;});
