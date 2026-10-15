const {launch}=require('./browser-harness.cjs');
(async()=>{
 const qa=await launch('bootstrap');try{
  const {page:admin}=await qa.context('admin');
  await qa.step('Administrador principal inicia sesión real',admin,()=>qa.login(admin,qa.fixture.admin.email));
  const created=[];
  for(const worker of qa.workers){
   await qa.step('ADMIN crea '+worker.key+' desde Usuarios',admin,async()=>{
    await admin.goto(qa.base+'/admin/user/index');
    if(await admin.getByRole('row').filter({hasText:worker.email}).count()===0){
    await admin.getByRole('link',{name:'+ Agregar Usuario',exact:true}).click();
    const form=admin.locator('#formCreateUser');await form.locator('[name=nombre_usuario]').fill(worker.name);await form.locator('[name=dni]').fill(worker.dni);
    await form.locator('[name=email]').fill(worker.email);await form.locator('[name=password]').fill(qa.secret.password);
    const response=admin.waitForResponse(r=>r.url().endsWith('/admin/user/store')&&r.request().method()==='POST');
    await form.locator('input[type=submit]').click();const result=await (await response).json();qa.assert.equal(result.code,1,'User create failed without revealing credentials.');
    await admin.getByRole('row').filter({hasText:worker.email}).waitFor();
    }
    const row=admin.getByRole('row').filter({hasText:worker.email});const href=await row.getByRole('link',{name:'Asignar Rol',exact:true}).getAttribute('href');worker.id=Number(href.split('/').pop());
    await row.getByRole('link',{name:'Asignar Rol',exact:true}).click();await admin.locator('input[name=role][value="'+qa.fixture.roles[worker.role]+'"]').check();
    await Promise.all([admin.waitForURL('**/admin/user/index'),admin.locator('input[type=submit]').click()]);
    await admin.getByRole('row').filter({hasText:worker.email}).getByText(worker.role,{exact:true}).waitFor();created.push(worker);
   });
  }
  require('node:fs').writeFileSync(qa.out+'/browser-created-workers.json',JSON.stringify(created,null,2));
  for(const worker of created){const {context,page}=await qa.context(worker.key);await qa.step(worker.key+' mantiene cookie y autenticación independientes',page,()=>qa.login(page,worker.email));qa.report.contexts[worker.key]={user_id:worker.id,independent_context:true};}
  const snapshot=qa.readback();qa.assert.equal(snapshot.counts.users,7);qa.assert.equal(snapshot.counts.payments,0);qa.assert.equal(snapshot.historical_intact,true);
  require('node:fs').writeFileSync(qa.out+'/browser-bootstrap-db.json',JSON.stringify(snapshot,null,2));
  await admin.goto(qa.base+'/admin/user/index');await admin.screenshot({path:qa.out+'/rc-workers-created.png',fullPage:true});
  await qa.finish();
 }catch(error){await qa.fail(error);}
})().catch(error=>{console.error('QA harness initialization failed. '+error.message);process.exitCode=1;});
