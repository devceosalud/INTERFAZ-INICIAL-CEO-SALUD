const test=require('node:test'),assert=require('node:assert/strict');
const editor=require('../../public/js/admin/master/catalog-editor.js');
test('Catalog editor sends session credentials, CSRF and exact persisted decimal fields',async()=>{
 const original={fetch:global.fetch,document:global.document};let sent;
 global.document={querySelector:()=>({content:'qa-csrf'})};global.fetch=async(url,options)=>{sent={url,options};return {ok:true,json:async()=>({code:1,msg:'Guardado'})};};
 try{const result=await editor.request('/master/admin/doctor/services/update',{method:'POST',body:'form-exact-decimals'});assert.equal(sent.url,'/master/admin/doctor/services/update');assert.equal(sent.options.credentials,'same-origin');assert.equal(sent.options.headers['X-CSRF-TOKEN'],'qa-csrf');assert.equal(sent.options.headers.Accept,'application/json');assert.equal(sent.options.body,'form-exact-decimals');assert.equal(result.code,1);}finally{Object.assign(global,original);}
});
test('Catalog editor preserves validation details and refuses HTML/session expiry as a successful save',async()=>{
 const original={fetch:global.fetch,document:global.document};global.document={querySelector:()=>null};
 try{global.fetch=async()=>({ok:false,json:async()=>({errors:{catalog_version:['Actualizar pantalla']}})});await assert.rejects(editor.request('/qa'),e=>e.body.errors.catalog_version[0]==='Actualizar pantalla');global.fetch=async()=>({ok:true,json:async()=>{throw new Error('HTML login');}});await assert.rejects(editor.request('/qa'),/Conserva los datos/);}finally{Object.assign(global,original);}
});
test('Catalogue validation summary includes all fields and never interpolates HTML',()=>{assert.deepEqual(editor.messages({errors:{precio_estandar:['Importe inválido'],catalog_version:'<script>no</script>'}}),[{key:'precio_estandar',message:'Importe inválido'},{key:'catalog_version',message:'<script>no</script>'}]);});
