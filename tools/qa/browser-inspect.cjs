const {launch}=require('./browser-harness.cjs');
(async()=>{const qa=await launch('inspect');try{
 const {page}=await qa.context('admin');
 for(const url of ['/admin/role','/admissionist/doctor-schedule','/scheduling-mvp/agenda','/scheduling-mvp/admision']){
  await page.goto(qa.base+url); console.log(JSON.stringify({url,status:page.url(),text:(await page.locator('body').innerText()).slice(0,9000),controls:await page.locator('button,a,input,select').evaluateAll(nodes=>nodes.map(n=>({tag:n.tagName,id:n.id,name:n.name,text:n.textContent.trim().slice(0,70),href:n.getAttribute('href'),type:n.type})).filter(n=>n.id||n.href?.includes('/admin/role')))},null,2));
 }await qa.finish();
 }catch(e){await qa.fail(e);}})();
