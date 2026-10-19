(function(root,factory){const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else root.AgendaAppointmentIndicators=api;}(typeof window!=='undefined'?window:globalThis,function(){
'use strict';
const priorities={DESACTIVADA:0,REEMBOLSO_SOLICITADO:1,SALIDA_REGISTRADA:2,LISTO_REEVALUACION:3,CONCILIAR_PAGO:4,TELECONSULTA:5,HERIDA:6,POSTOPERATORIO:7,ADICIONAL:8,FUERA_HORARIO:9};
function split(badges,limit=3){const unique=[...new Map((badges||[]).map(b=>[b.clave,b])).values()].sort((a,b)=>(priorities[a.clave]??20)-(priorities[b.clave]??20));return {visible:unique.filter((b,i)=>i<limit||(priorities[b.clave]??20)<5),extra:unique.filter((b,i)=>i>=limit&&(priorities[b.clave]??20)>=5)};}
function append(doc,parent,badges,{limit=3,compact=false}={}){
 const {visible,extra}=split(badges,limit);if(!visible.length)return;const group=doc.createElement('span');group.className='agenda-indicators';
 const chip=b=>{const n=doc.createElement('small');n.className='agenda-indicator';n.textContent=compact?({POSTOPERATORIO:'POSTOP',HERIDA:'HERIDA',FUERA_HORARIO:'FH',LISTO_REEVALUACION:'REEVALUACIÓN'}[b.clave]||b.etiqueta):b.etiqueta;n.style.backgroundColor=b.fondo;n.style.color=b.color;n.title=b.descripcion||b.etiqueta;n.setAttribute('aria-label',b.etiqueta);return n;};
 visible.forEach(b=>group.appendChild(chip(b)));if(extra.length){const more=doc.createElement('details'),summary=doc.createElement('summary'),body=doc.createElement('span');more.className='agenda-indicators-more';summary.textContent='+'+extra.length;summary.setAttribute('aria-label',extra.length+' indicadores adicionales');extra.forEach(b=>body.appendChild(chip(b)));more.append(summary,body);more.addEventListener('click',e=>e.stopPropagation());more.addEventListener('keydown',e=>e.stopPropagation());group.appendChild(more);}parent.appendChild(group);
}
return {split,append};}));
