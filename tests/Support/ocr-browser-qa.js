'use strict';
const engine=AppointmentReceiptOCR.createEngine(), fields=['amount','operation','bank','date','time'];
const byId=id=>document.getElementById(id);
let selectedFile=null, imageUrl=null, generation=0;
async function clear() { generation++; await engine.cancel(); fields.forEach(f=>byId(f).value='');byId('reviewed').checked=false;byId('metrics').textContent=''; }
async function select(file) { await clear(); selectedFile=file; if(imageUrl) URL.revokeObjectURL(imageUrl); imageUrl=URL.createObjectURL(file);byId('preview').src=imageUrl;byId('preview').hidden=false;byId('read').disabled=false;byId('status').textContent='Imagen lista. Requiere revisión humana.'; }
byId('receipt-file').addEventListener('change',e=>{if(e.target.files[0])void select(e.target.files[0]);});
byId('load-fixture').addEventListener('click',async()=>{ const name=byId('fixture').value;const response=await fetch('/fixture/'+name);if(!response.ok)throw new Error('Fixture no disponible');await select(new File([await response.blob()],name,{type:name.endsWith('.jpg')?'image/jpeg':'image/png'})); });
byId('cancel').addEventListener('click',async()=>{await clear();byId('status').textContent='Lectura descartada.';});
byId('read').addEventListener('click',async()=>{
 const version=++generation; const start=performance.now();byId('read').disabled=true;byId('reviewed').checked=false;
 try { const r=await engine.recognize(selectedFile,p=>byId('status').textContent='Lectura local: '+Math.round(p*100)+'%'); if(version!==generation)return;
 fields.forEach(f=>byId(f).value=r[f]||'');byId('status').textContent='Lectura terminada. Verifica todos los datos antes de cualquier registro.';
 byId('metrics').textContent=JSON.stringify({confidence:r.confidence,ambiguous:r.ambiguous,needs_review:r.needs_review,elapsed_ms:Math.round(performance.now()-start)},null,2);
 }catch(e){if(version===generation)byId('status').textContent=e.message;}finally{byId('read').disabled=!selectedFile;}
});
