const fs=require('node:fs');
function helpers(qa){
 const direct=(page,url,method='POST',data={},csrf=true)=>page.evaluate(async({url,method,data,csrf})=>{const r=await fetch(url,{method,headers:{'Content-Type':'application/json',Accept:'application/json',...(csrf?{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}:{})},body:JSON.stringify(data)});return {status:r.status,body:await r.json().catch(()=>({non_json:true}))};},{url,method,data,csrf});
 async function permissions(admin,role,changes){await admin.goto(qa.base+'/admin/role/edit/'+qa.fixture.roles[role]);for(const [cap,enabled]of Object.entries(changes))await admin.getByLabel(cap,{exact:true}).setChecked(enabled);admin.once('dialog',dialog=>dialog.accept());await Promise.all([admin.waitForURL('**/admin/role'),admin.locator('input[value="Actualizar Permisos"]').click()]);}
 async function agenda(page){await page.goto(qa.base+'/scheduling-mvp/agenda');await page.locator('#agenda-doctor-2').check({force:true});await page.locator('.agenda-day-row[data-row-start="09:00"]').waitFor();}
 async function select(page,id){await page.locator('.agenda-day-entry[data-appointment-id="'+id+'"]').first().click();await page.locator('#agenda-op-price').waitFor();await page.locator('#agenda-op-payment-panel > summary').click();}
 async function image(page,bank,amount,operation,file){const p=await page.context().newPage();await p.setViewportSize({width:700,height:700});await p.setContent('<html><body style="font:32px Arial;padding:35px;background:white;color:black"><h1>'+bank+'</h1><p>COMPROBANTE FICTICIO QA</p><h2>Transferencia exitosa</h2><p>Monto: S/ '+amount+'</p><p>Nro. de operacion: '+operation+'</p><p>14/10/2026 10:30</p><p>SIN VALIDEZ BANCARIA</p></body></html>');await p.screenshot({path:qa.out+'/'+file});await p.close();return qa.out+'/'+file;}
 const save=(name,value)=>fs.writeFileSync(qa.out+'/'+name+'.json',JSON.stringify(value,null,2));
 return {direct,permissions,agenda,select,image,save};
}
module.exports={helpers};
