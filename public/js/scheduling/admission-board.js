(function(root,factory){
    const api=factory();
    if(typeof module==='object'&&module.exports) module.exports=api;
    else {root.AdmissionBoard=api;document.addEventListener('DOMContentLoaded',()=>api.start(document));}
}(typeof window!=='undefined'?window:globalThis,function(){
    'use strict';
    const careLabels={PROGRAMADO:'Programado',CONFIRMADO:'Confirmado (atención)',PACIENTE_LLEGO:'Paciente llegó',EN_ESPERA:'En espera',LLAMANDO:'Llamando',EN_ATENCION:'En consultorio',ATENDIDO:'Atendido',CANCELADO:'Cancelado',NO_ASISTIO:'No asistió',RETIRO:'Retiro',REEVALUACION:'Reevaluación (estado heredado)'};
    const actionLabels={EN_ESPERA:'Registrar llegada',LLAMANDO:'Registrar llamado',EN_ATENCION:'Iniciar atención',ATENDIDO:'Finalizar atención'};
    function latest(){let revision=0,controller=null;return {cancel(){revision++;controller?.abort();},async run(work,commit){controller?.abort();controller=new AbortController();const current=++revision;try{const value=await work(controller.signal);if(current!==revision)return false;await commit(value);return true;}catch(error){if(current!==revision||error.name==='AbortError')return false;throw error;}}};}
    function money(value){return 'S/ '+Number(value||0).toLocaleString('es-PE',{minimumFractionDigits:2,maximumFractionDigits:2});}
    function bookingLabel(state){return state==='CONFIRMADA'?'Horario confirmado':state==='PENDIENTE_CONFIRMACION'?'Reserva pendiente · horario sin confirmar':state==='LEGADO'?'Agenda heredada':String(state||'Sin clasificación');}
    function time(value){if(!value)return 'Sin registro';const date=new Date(value);return Number.isNaN(date.getTime())?'Sin registro':new Intl.DateTimeFormat('es-PE',{timeZone:'America/Lima',hour:'2-digit',minute:'2-digit',hour12:false}).format(date);}
    function start(doc){
        const board=doc.getElementById('admission-board');if(!board)return;
        const $=id=>doc.getElementById('admission-'+id), form=$('filters');
        const listRequest=latest(),detailRequest=latest(),availabilityRequest=latest();
        let page=1,total=0,selected=null,busy=false,detail=null,filtersSnapshot='';
        const node=(tag,text,className)=>{const n=doc.createElement(tag);if(text!==undefined&&text!==null)n.textContent=String(text);if(className)n.className=className;return n;};
        const append=(parent,...children)=>{children.forEach(child=>parent.appendChild(child));return parent;};
        const link=(label,url,primary=false)=>{const a=node('a',label,'admission-btn'+(primary?' admission-btn--primary':''));a.href=url;return a;};
        const tag=(text,kind='')=>node('span',text,'admission-tag'+(kind?' admission-tag--'+kind:''));
        const notice=text=>{$('notice').textContent=text||'';};
        const data=()=>Object.fromEntries(new FormData(form).entries());
        const agendaUrl=(doctorId,fecha)=>{const u=new URL(board.dataset.agenda,location.origin);u.searchParams.set('fecha',fecha);if(doctorId)u.searchParams.set('doctor_id',doctorId);return u.href;};
        async function request(url,options={}){const response=await fetch(url,{...options,headers:{Accept:'application/json',...(options.body?{'Content-Type':'application/json'}:{}),...options.headers}});if(!response.ok){let message=response.status===403?'No tienes permiso para esta operación.':response.status===404?'La cita no está disponible.':'No se pudo completar la consulta. Actualiza y vuelve a intentar.';if(response.status===409||response.status===422){const body=await response.json().catch(()=>({}));message=Object.values(body.errors||{}).flat()[0]||body.message||message;}throw new Error(message);}return response.json();}
        function clearDetail(){selected=null;detail=null;detailRequest.cancel();$('detail').replaceChildren(node('p','Selecciona una cita para consultar su información y acciones autorizadas.','admission-empty'));}
        function description(label,value){const box=node('div');append(box,node('dt',label),node('dd',value??'—'));return box;}
        function section(title){const box=node('section');box.appendChild(node('h4',title));return box;}
        function economic(dl,e){append(dl,description('Precio neto',money(e.precio)),description(e.fuente==='LEGACY_SNAPSHOT'?'Abonado histórico':'Pagos registrados',e.fuente==='UNALLOCATED'?'Por conciliar':money(e.pagado)),description('Crédito aplicado',money(e.credito)),description('Saldo',e.fuente==='UNALLOCATED'?'Por conciliar':money(e.saldo)));}
        function renderRows(payload){
            total=payload.total;page=payload.page;const body=$('rows');body.replaceChildren();
            for(const row of payload.rows){const tr=node('tr');tr.dataset.appointmentId=row.id;if(row.id===selected)tr.className='is-selected';
                const cell=(text,small)=>{const td=node('td',text);if(small)td.appendChild(node('small',small));tr.appendChild(td);return td;};
                cell(row.hora,row.fecha);cell(row.patient,'H.C. '+(row.historia||'—')+' · '+(row.documento||'—'));
                cell(row.doctor,row.servicio);const status=cell();append(status,tag(careLabels[row.estado_cita]||row.estado_cita,row.grupo==='desactivadas'?'inactive':''),node('small',bookingLabel(row.estado_agenda)));if(row.postoperatorio)status.appendChild(tag('Postoperatorio'));if(row.grupo==='otros')status.appendChild(tag('Revisar clasificación','review'));
                cell(time(row.tiempos.llegada)+' / '+time(row.tiempos.inicio)+' / '+time(row.tiempos.fin));
                const e=row.economy;cell(money(e.precio)+' / '+(e.fuente==='UNALLOCATED'?'Por conciliar':money(e.pagado))+' / '+(e.fuente==='UNALLOCATED'?'Por conciliar':money(e.saldo)),e.fuente==='LEGACY_SNAPSHOT'?'Abonado histórico sin conciliación':e.credito!=='0.00'?'Crédito '+money(e.credito):e.fuente==='UNALLOCATED'?'Atribución pendiente':row.estado_pagado);
                cell(row.responsable||'—',row.sede||'Sede sin registrar');const button=node('button','Ver','admission-btn');button.type='button';button.setAttribute('aria-label','Ver cita de '+row.patient+' a las '+row.hora);button.addEventListener('click',()=>select(row.id));cell().appendChild(button);body.appendChild(tr);
            }
            if(!payload.rows.length){const tr=node('tr'),td=node('td','No hay citas visibles que coincidan con estos filtros.');td.colSpan=8;tr.appendChild(td);body.appendChild(tr);}
            for(const [key,count]of Object.entries(payload.kpis)){const target=board.querySelector('[data-kpi="'+key+'"]');if(target)target.textContent=count;}
            $('count').textContent='· '+payload.total;
            $('reservations').textContent=payload.kpis.reservas+' reservas pendientes · '+payload.kpis.otros+' para revisar';
            $('page').textContent='Página '+page+' de '+Math.max(1,Math.ceil(total/30));$('prev').disabled=page<=1;$('next').disabled=page*30>=total;
            const totals=$('totals');totals.replaceChildren();const labels={precio:'Precio neto',pagos_reales:'Pagos acreditados',pagado_historico:'Abonado histórico',credito:'Crédito aplicado',saldo:'Saldo atribuido'};
            for(const[key,label]of Object.entries(labels))totals.appendChild(description(label,money(payload.totals[key])));
            if(payload.totals.requieren_revision)totals.appendChild(description('Atribución pendiente',payload.totals.requieren_revision+' citas'));
        }
        async function select(id){if(busy)return;selected=id;detail=null;$('detail').replaceChildren(node('p','Consultando cita…','admission-empty'));
            board.querySelectorAll('tr[data-appointment-id]').forEach(tr=>tr.classList.toggle('is-selected',Number(tr.dataset.appointmentId)===id));
            try{await detailRequest.run(signal=>request(board.dataset.base+'/'+id,{signal}),payload=>{detail=payload;renderDetail(payload);});}catch(error){$('detail').replaceChildren(node('p',error.message,'admission-empty'));}
        }
        function renderDetail(payload){const root=$('detail'),a=payload.appointment;root.replaceChildren();
            const head=section('CITA '+a.numero);append(head,node('h3',a.patient),tag(careLabels[a.estado_cita]||a.estado_cita,a.grupo==='desactivadas'?'inactive':''),node('p',bookingLabel(a.estado_agenda)),node('p',a.fecha+' · '+a.hora),node('small',a.doctor+' · '+a.servicio),node('small',a.sede||'Sede sin registrar; no se ha reasignado'));root.appendChild(head);
            const contact=section('Contacto y responsable');append(contact,node('p',payload.contact.telefono||'Teléfono sin registrar'),node('small',payload.contact.telefono_secundario||''),node('p','Responsable: '+(a.responsable||'—')),node('small','Canal: '+(payload.contact.canal||'—')+' · Medio: '+(payload.contact.medio||'—')));root.appendChild(contact);
            const journey=section('Recorrido de atención'),list=node('ol',null,'admission-journey');for(const[key,label]of Object.entries({llegada:'Llegada',llamado:'Llamado',inicio:'Inicio de atención',fin:'Fin de atención'}))append(list,append(node('li'),node('time',time(a.tiempos[key])),node('span',label)));journey.appendChild(list);root.appendChild(journey);
            const pricing=section('Situación económica'),dl=node('dl');economic(dl,a.economy);append(pricing,dl,node('small','Fuente: '+a.economy.fuente),node('small','Regla de precio registrada: '+(a.pricing_rule||'Histórica / sin clasificación')));root.appendChild(pricing);
            const actions=section('Operaciones'),buttons=node('div',null,'admission-detail-actions');
            if(a.grupo!=='desactivadas')buttons.appendChild(link('Abrir cita · pago / OCR / confirmar',a.agenda_url,true));
            buttons.appendChild(link('Ficha administrativa del paciente',payload.patient_url));
            for(const state of payload.care_actions){const button=node('button',actionLabels[state],'admission-btn');button.type='button';button.addEventListener('click',()=>transition(state));buttons.appendChild(button);}append(actions,buttons,node('small','Cancelación, inasistencia y retiro: utiliza las acciones autorizadas de Agenda. No implican devolución automática.'));root.appendChild(actions);
            const docs=section('Comprobantes de esta cita');if(!payload.documents.length)docs.appendChild(node('p','Sin comprobantes adjuntos al pago.'));for(const d of payload.documents){const p=node('p');p.appendChild(link('Comprobante del pago #'+d.payment_id,d.download_url));docs.appendChild(p);}docs.appendChild(node('small','Recetas e informes protegidos no se muestran en este panel.'));root.appendChild(docs);
            const events=section('Actividad registrada'),ul=node('ul',null,'admission-events');for(const e of payload.events)append(ul,append(node('li'),node('span',new Date(e.at).toLocaleString('es-PE',{timeZone:'America/Lima'})+' · '+e.type.replaceAll('_',' ')),node('small',e.actor||'Sin responsable registrado')));if(!payload.events.length)ul.appendChild(node('li','Sin eventos registrados.'));events.appendChild(ul);root.appendChild(events);
            const history=node('details');history.appendChild(node('summary','Historial administrativo · hasta 20 citas visibles'));for(const h of payload.history){const item=node('div',null,'admission-history-item');append(item,node('strong',h.fecha+' · '+h.hora),node('p',h.doctor+' · '+h.servicio),node('small',(careLabels[h.estado_cita]||h.estado_cita)+' · '+bookingLabel(h.estado_agenda)),node('p','Precio '+money(h.economy.precio)+' · Saldo '+(h.economy.fuente==='UNALLOCATED'?'Por conciliar':money(h.economy.saldo))));history.appendChild(item);}if(!payload.history.length)history.appendChild(node('p','Sin otras citas visibles.'));root.appendChild(history);
        }
        async function transition(state){if(busy||!detail)return;const id=detail.appointment.id,expected=detail.appointment.estado_cita;
            if(!window.Swal){notice('No se cargó la confirmación de acciones. Vuelve a abrir el panel.');return;}
            const result=await window.Swal.fire({title:actionLabels[state],text:'Se registrará la actuación y su responsable. No cambia pagos ni el precio.',icon:'question',showCancelButton:true,confirmButtonText:'Confirmar',cancelButtonText:'Volver'});if(!result.isConfirmed||busy||selected!==id||detail?.appointment.estado_cita!==expected)return;
            busy=true;board.querySelectorAll('button').forEach(b=>b.disabled=true);try{await request(board.dataset.base+'/'+id+'/care',{method:'POST',body:JSON.stringify({state,expected_state:expected,request_key:crypto.randomUUID()})});busy=false;await load(id);notice('Atención actualizada. Indicadores y saldo consultados nuevamente.');}catch(error){notice(error.message);busy=false;await select(id);}finally{board.querySelectorAll('button').forEach(b=>b.disabled=false);$('prev').disabled=page<=1;$('next').disabled=page*30>=total;}
        }
        async function availability(filters){
            const options=Array.from($('doctor').options).filter(o=>o.value&&o.dataset.active==='1'&&(!filters.doctor_id||o.value===filters.doctor_id)&&(!filters.specialty_id||o.dataset.specialty===filters.specialty_id)).slice(0,6);
            $('availability-note').textContent='Primeros '+options.length+' médicos activos del filtro. Disponibilidad informativa; se revalida al reservar.';
            $('availability').replaceChildren();
            try{await availabilityRequest.run(async signal=>Promise.all(options.map(async option=>{const u=new URL(board.dataset.availability,location.origin);u.searchParams.set('doctor_id',option.value);u.searchParams.set('fecha',filters.fecha);if(filters.site_id)u.searchParams.set('site_id',filters.site_id);try{return {option,result:await request(u.href,{signal})};}catch(error){if(error.name==='AbortError')throw error;return {option,error:true};}})),results=>{for(const entry of results){const card=node('article');card.appendChild(node('h3',entry.option.textContent));if(entry.error)card.appendChild(node('p','No se pudo consultar el horario.'));else{const slots=entry.result.slots||[],free=slots.filter(s=>s.estado==='DISPONIBLE');append(card,node('p',slots.length?free.length+' intervalos libres / '+slots.length+' intervalos del horario':'Sin horario configurado'),node('p',free.length?'Primer intervalo libre: '+free[0].inicio:'Sin intervalos libres'),link('Abrir Agenda',agendaUrl(entry.option.value,filters.fecha)));}$('availability').appendChild(card);}});}catch(error){$('availability-note').textContent='No se pudo consultar disponibilidad. Usa Agenda para verificar horarios.';}
        }
        async function load(reselect=null){if(busy)return;const filters=data();filters.page=page;clearDetail();availabilityRequest.cancel();$('rows').replaceChildren();notice('Consultando conjunto filtrado…');$('create').href=agendaUrl(filters.doctor_id,filters.fecha);
            try{await listRequest.run(signal=>request(board.dataset.feed,{method:'POST',signal,body:JSON.stringify(filters)}),async payload=>{filtersSnapshot=JSON.stringify(data());renderRows(payload);notice('');$('scope').textContent='Indicadores y totales: '+payload.total+' citas visibles del '+filters.fecha+' al '+filters.hasta+'. Incluyen todos los resultados de estos filtros, no solo esta página.';availability(filters);if(reselect&&payload.rows.some(a=>a.id===reselect))await select(reselect);});}catch(error){notice(error.message);$('count').textContent='';board.querySelectorAll('[data-kpi]').forEach(el=>el.textContent='—');$('totals').replaceChildren();}
        }
        form.addEventListener('submit',event=>{event.preventDefault();page=1;load();});$('refresh').addEventListener('click',()=>load(selected));
        $('prev').addEventListener('click',()=>{if(page>1){page--;load();}});$('next').addEventListener('click',()=>{if(page*30<total){page++;load();}});
        $('date').addEventListener('change',()=>{if($('until').value<$('date').value)$('until').value=$('date').value;});
        $('today').addEventListener('click',()=>{$('date').value=$('today').dataset.today;$('until').value=$('date').value;page=1;load();});
        $('specialty').addEventListener('change',()=>{for(const option of $('doctor').options)option.hidden=Boolean(option.value&&$('specialty').value&&option.dataset.specialty!==$('specialty').value);if($('doctor').selectedOptions[0]?.hidden)$('doctor').value='';});
        board.querySelectorAll('[data-group]').forEach(button=>button.addEventListener('click',()=>{$('group').value=button.dataset.group;page=1;load();}));
        window.addEventListener('focus',()=>{if(!busy&&JSON.stringify(data())===filtersSnapshot)load(selected);});
        load();
    }
    return {latest,bookingLabel,time,money,start};
}));
