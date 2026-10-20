(function(root,factory){const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else root.AgendaProfessionalPreferences=api;}(typeof window!=='undefined'?window:globalThis,function(){
'use strict';
function validIds(ids,allowed){return [...new Set((ids||[]).map(String))].filter(id=>allowed.has(id));}
function create({doc,initial,url,onSelect,onError}){
 let data=initial,write=Promise.resolve();const host=doc.getElementById('agenda-professional-shortcuts'),panel=doc.getElementById('agenda-professional-shortcuts-panel');
 const rows=[...doc.querySelectorAll('[data-doctor-row]')],allowed=new Set(rows.map(row=>row.dataset.doctorId));
 const node=(tag,text)=>{const n=doc.createElement(tag);if(text)n.textContent=text;return n;};
 function change(id,action){
  write=write.then(async()=>{const r=await fetch(url,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':doc.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({doctor_id:Number(id),action})});const result=await r.json();if(!r.ok)throw new Error(Object.values(result.errors||{}).flat()[0]||result.message||'No se pudo guardar la preferencia.');data=result;render();}).catch(e=>onError(e.message));return write;
 }
 function render(){host.replaceChildren();const favoriteIds=new Set(validIds(data.favorites,allowed));panel.hidden=!favoriteIds.size&&!validIds(data.frequent,allowed).length;
  for(const row of rows){let button=row.querySelector('[data-professional-favorite]');if(!button){button=node('button');button.type='button';button.dataset.professionalFavorite=row.dataset.doctorId;button.className='agenda-favorite-toggle';button.addEventListener('click',event=>{event.stopPropagation();const yes=validIds(data.favorites,allowed).includes(row.dataset.doctorId);change(row.dataset.doctorId,yes?'unfavorite':'favorite');});row.appendChild(button);}const yes=favoriteIds.has(row.dataset.doctorId);button.textContent=yes?'★':'☆';button.setAttribute('aria-pressed',String(yes));button.setAttribute('aria-label',(yes?'Quitar favorito: ':'Guardar favorito: ')+row.querySelector('label').textContent.trim());}
  for(const[key,title]of [['favorites','Favoritos'],['frequent','Frecuentes']]){const ids=validIds(data[key],allowed).filter(id=>!rows.find(row=>row.dataset.doctorId===id).hidden&&(key!=='frequent'||!favoriteIds.has(id))).slice(0,5);if(!ids.length)continue;
   const group=node('div');group.className='agenda-professional-shortcuts';group.appendChild(node('small',title));for(const id of ids){const row=rows.find(row=>row.dataset.doctorId===id),button=node('button',row.querySelector('.agenda-doctor-row__name').textContent);button.type='button';button.className='agenda-btn';button.title=button.textContent;button.addEventListener('click',async()=>{if(await onSelect(id)){await change(id,'select');panel.open=false;}});group.appendChild(button);}host.appendChild(group);}
 }
 render();return {render,selected:id=>change(String(id),'select'),last:()=>validIds([data.last],allowed)[0]||null};
}
return {create,validIds};}));
