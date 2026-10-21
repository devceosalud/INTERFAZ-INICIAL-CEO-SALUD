(function(root,factory){const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else root.AgendaRescheduleHistory=api;}(typeof window!=='undefined'?window:globalThis,function(){
'use strict';
const date=value=>String(value||'').split('-').reverse().join('/');
function label(item){return item.from_time+' · CITA REPROGRAMADA → '+date(item.to_date)+' · '+item.to_time;}
function render(doc,payload,doctorIds,onOpen,onMore){const panel=doc.getElementById('agenda-reschedule-history'),list=doc.getElementById('agenda-reschedule-history-list');if(!panel||!list)return;const items=(payload?.items||[]).filter(item=>doctorIds.map(String).includes(String(item.doctor_id)));panel.hidden=!items.length&&!payload?.has_more;doc.getElementById('agenda-reschedule-count').textContent='('+items.length+(payload?.has_more?'+':'')+')';const historyKey=items.map(i=>i.event_id).join(',');if(panel.dataset.historyKey!==historyKey){panel.open=items.length>0&&items.length<=3;panel.dataset.historyKey=historyKey;}list.replaceChildren();list.className='agenda-reschedule-history-list';
 for(const item of items){const row=doc.createElement('div');row.className='agenda-reschedule-reference';row.dataset.historyEventId=item.event_id;const text=doc.createElement('span');text.textContent=date(item.from_date)+' · '+label(item)+(item.to_date!==item.current_date||item.to_time!==item.current_time?' · Actual: '+date(item.current_date)+' · '+item.current_time:'');text.title='Referencia histórica sin ocupación. Cita #'+item.appointment_id+(item.actor?' · '+item.actor:'');const see=doc.createElement('button');see.type='button';see.className='agenda-btn';see.textContent='Ver cita actual';see.addEventListener('click',()=>onOpen(item));row.append(text,see);list.appendChild(row);}
 if(payload?.has_more&&onMore){const more=doc.createElement('button');more.type='button';more.className='agenda-btn';more.textContent='Ver referencias anteriores';more.addEventListener('click',async()=>{more.disabled=true;try{await onMore(payload.next_cursor);}finally{if(more.isConnected)more.disabled=false;}});list.appendChild(more);}
}
function forRow(items,doctorId,day,start,end){return(items||[]).filter(i=>String(i.doctor_id)===String(doctorId)&&i.from_date===day&&i.from_time<end&&(i.from_end>i.from_time?i.from_end>start:i.from_time>=start));}
function marker(doc,item,availability,onOpen){
 const box=doc.createElement('aside');box.className='agenda-rescheduled-marker';box.dataset.historyEventId=item.event_id;box.setAttribute('aria-label','Referencia histórica de reprogramación; no ocupa el intervalo');
 const text=doc.createElement('strong');text.textContent=item.from_time+(item.duration_minutes?'–'+item.from_end:'')+' · REPROGRAMADA → '+date(item.to_date)+', '+item.to_time;box.appendChild(text);
 const note=doc.createElement('small');note.textContent=(item.to_date!==item.current_date||item.to_time!==item.current_time?'Cita vigente: '+date(item.current_date)+', '+item.current_time+'. ':'')+(availability==='available'?'Intervalo liberado y disponible para otra cita.':availability==='off-hours'?'Referencia histórica · actualmente fuera del horario configurado.':'Referencia histórica · la disponibilidad actual se muestra en la cita o intervalo vigente.');box.appendChild(note);
 const see=doc.createElement('button');see.type='button';see.className='agenda-btn';see.textContent='Ver cita vigente';see.addEventListener('click',e=>{e.preventDefault();onOpen(item);});box.appendChild(see);
 box.addEventListener('click',e=>e.stopPropagation());box.addEventListener('keydown',e=>e.stopPropagation());return box;
}
function calendarIndicators(doc,host,payload,doctorIds,onShow){
 host.querySelectorAll('.agenda-history-count').forEach(n=>n.remove());const groups=new Map();
 for(const item of payload?.items||[]){if(!doctorIds.map(String).includes(String(item.doctor_id)))continue;if(!groups.has(item.from_date))groups.set(item.from_date,[]);groups.get(item.from_date).push(item);}
 for(const[day,items]of groups){for(const cell of host.querySelectorAll('[data-date="'+day+'"]')){
  const target=cell.querySelector('.fc-daygrid-day-top')||cell.querySelector('.fc-scrollgrid-sync-inner');if(!target)continue;
  const b=doc.createElement('button');b.type='button';b.className='agenda-history-count';b.textContent='↪ '+items.length;b.setAttribute('aria-label',items.length+' reprogramaciones desde '+date(day)+' · historial sin ocupación');b.title=items.map(label).join('\n');b.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();onShow(day);});b.addEventListener('keydown',e=>e.stopPropagation());target.appendChild(b);
 }}
}
return {label,render,forRow,marker,calendarIndicators};}));
