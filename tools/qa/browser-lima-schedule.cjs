const {launch}=require('./browser-harness.cjs');
(async()=>{const qa=await launch('lima-schedule');try{const {page}=await qa.context('admin');
await qa.step('Horario del día local de Lima creado desde formulario; fechas de UI coherentes',page,async()=>{
 await page.goto(qa.base+'/admissionist/doctor-schedule');await page.locator('#schedule-add').click();qa.assert.equal(await page.locator('#schedule-date').inputValue(),qa.fixture.date);
 for(const [id,value] of Object.entries({'schedule-doctor':'2','schedule-site':String(qa.fixture.site_id),'schedule-start-hour':'08','schedule-start-minute':'00','schedule-end-hour':'18','schedule-end-minute':'00','schedule-duration':'30'}))await page.locator('#'+id).selectOption(value,{force:true});
 const response=page.waitForResponse(r=>r.url().endsWith('/doctor-schedule/store')&&r.request().method()==='POST');await page.locator('#schedule-save').click();await page.locator('.swal2-confirm').click();const r=await response,body=await r.json();qa.assert.equal(r.status(),200);qa.assert.equal(body.code,1,JSON.stringify(body));await page.locator('#schedule-editor').waitFor({state:'hidden'});
 await page.goto(qa.base+'/scheduling-mvp/agenda');qa.assert.equal(await page.locator('#agenda-date').inputValue(),qa.fixture.date);
 });await qa.finish();}catch(e){await qa.fail(e);}})();
