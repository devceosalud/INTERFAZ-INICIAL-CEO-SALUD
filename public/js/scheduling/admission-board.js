(function(root,factory){
    const api=factory();
    if(typeof module==='object'&&module.exports) module.exports=api;
    else {root.AdmissionBoard=api;document.addEventListener('DOMContentLoaded',()=>api.start(document));}
}(typeof window!=='undefined'?window:globalThis,function(){
    'use strict';
    const careLabels={PROGRAMADO:'Programado',CONFIRMADO:'Confirmado (atención)',PACIENTE_LLEGO:'Paciente llegó',EN_ESPERA:'En espera',LLAMANDO:'Llamando',EN_ATENCION:'En consultorio',ATENDIDO:'Atendido',CANCELADO:'Cancelado',NO_ASISTIO:'No asistió',RETIRO:'Retiro',REEVALUACION:'Reevaluación (estado heredado)'};
    const actionLabels={EN_ESPERA:'Registrar llegada',LLAMANDO:'Registrar llamado',EN_ATENCION:'Iniciar atención',ATENDIDO:'Finalizar atención',SALIDA_REGISTRADA:'Registrar salida'};
    function latest(){let revision=0,controller=null;return {cancel(){revision++;controller?.abort();},async run(work,commit){controller?.abort();controller=new AbortController();const current=++revision;try{const value=await work(controller.signal);if(current!==revision)return false;await commit(value);return true;}catch(error){if(current!==revision||error.name==='AbortError')return false;throw error;}}};}
    function polling({run,enabled=()=>true,alive=()=>true,onError=()=>{},schedule=setTimeout,clear=clearTimeout,interval=5000}){
        let stopped=true,timer=null,running=false;
        function arm(delay){if(!stopped&&timer===null&&!running)timer=schedule(tick,delay);}
        async function tick(){timer=null;if(stopped)return;if(!alive()){stop();return;}
            if(!enabled()){arm(interval);return;}running=true;
            try{await run();}catch(error){onError(error);}finally{running=false;arm(interval);}
        }
        function stop(){stopped=true;if(timer!==null)clear(timer);timer=null;}
        return {start(immediate=false){stopped=false;if(immediate&&timer!==null){clear(timer);timer=null;}arm(immediate?0:interval);},stop};
    }
    function money(value){return 'S/ '+Number(value||0).toLocaleString('es-PE',{minimumFractionDigits:2,maximumFractionDigits:2});}
    function bookingLabel(state){return state==='CONFIRMADA'?'Horario confirmado':state==='PENDIENTE_CONFIRMACION'?'Reserva pendiente · horario sin confirmar':state==='LEGADO'?'Agenda heredada':String(state||'Sin clasificación');}
    function time(value){if(!value)return 'Sin registro';const date=new Date(value);return Number.isNaN(date.getTime())?'Sin registro':new Intl.DateTimeFormat('es-PE',{timeZone:'America/Lima',hour:'2-digit',minute:'2-digit',hour12:false}).format(date);}
    async function readResponse(response){
        const generic='No se pudo completar la consulta. Actualiza y vuelve a intentar.';
        const jsonType=(response.headers?.get('content-type')||'').includes('application/json');
        if([401,419].includes(response.status)||response.redirected||(response.ok&&!jsonType))throw new Error('La sesión no está disponible. Inicia sesión nuevamente.');
        if(!response.ok){let message=response.status===403?'No tienes permiso para esta operación.':response.status===404?'La cita no está disponible.':generic;
            if(jsonType&&(response.status===409||response.status===422)){const body=await response.json().catch(()=>({}));message=Object.values(body.errors||{}).flat()[0]||body.message||message;}
            throw new Error(message);
        }
        try{return await response.json();}catch(error){throw new Error(generic);}
    }
    function start(doc){
        const board=doc.getElementById('admission-board');if(!board)return;
        const $=id=>doc.getElementById('admission-'+id), form=$('filters');
        const listRequest=latest(),detailRequest=latest(),availabilityRequest=latest();
        let page=1,total=0,selected=null,busy=false,confirming=false,detail=null,filtersSnapshot='',revision='',loading=false,pendingLoad=null;
        const node=(tag,text,className)=>{const n=doc.createElement(tag);if(text!==undefined&&text!==null)n.textContent=String(text);if(className)n.className=className;return n;};
        const append=(parent,...children)=>{children.forEach(child=>parent.appendChild(child));return parent;};
        const link=(label,url,primary=false)=>{const a=node('a',label,'admission-btn'+(primary?' admission-btn--primary':''));a.href=url;return a;};
        const tag=(text,kind='')=>node('span',text,'admission-tag'+(kind?' admission-tag--'+kind:''));
        const notice=text=>{$('notice').textContent=text||'';};
        const data=()=>Object.fromEntries(new FormData(form).entries());
        const agendaUrl=(doctorId,fecha)=>{const u=new URL(board.dataset.agenda,location.origin);u.searchParams.set('fecha',fecha);if(doctorId)u.searchParams.set('doctor_id',doctorId);return u.href;};
        async function request(url,options={}){return readResponse(await fetch(url,{credentials:'same-origin',...options,headers:{Accept:'application/json','X-CSRF-TOKEN':doc.querySelector('meta[name="csrf-token"]')?.content||'',...(options.body?{'Content-Type':'application/json'}:{}),...options.headers}}));}
        function clearDetail(){selected=null;detail=null;detailRequest.cancel();$('detail').replaceChildren(node('p','Selecciona una cita para consultar su información y acciones autorizadas.','admission-empty'));}
        function description(label,value){const box=node('div');append(box,node('dt',label),node('dd',value??'—'));return box;}
        function section(title){const box=node('section');box.appendChild(node('h4',title));return box;}
        function economic(dl,e){append(dl,description('Precio neto',money(e.precio)),description(e.fuente==='LEGACY_SNAPSHOT'?'Abonado histórico':'Pagos registrados',e.fuente==='UNALLOCATED'?'Por conciliar':money(e.pagado)),description('Crédito aplicado',money(e.credito)),description('Saldo',e.fuente==='UNALLOCATED'?'Por conciliar':money(e.saldo)));}
        function renderOperationalTasks(payload) {
            const canManage = payload.can_manage_operational_tasks === true;
            const canComplete = payload.can_complete_operational_tasks === true;
            if (!canManage && !canComplete) return null;

            const appointmentId = Number(payload.appointment.id);
            const area = section('Observaciones y pendientes');
            area.classList.add('admission-task-panel', 'clarity-mask');
            area.appendChild(node('p',
                'Seguimiento operativo de esta cita. No sustituye una orden médica ni la historia clínica.',
                'admission-task-help'));
            const tasks = payload.operational_tasks || [];
            if (!tasks.length) area.appendChild(node('p','Sin observaciones pendientes o registradas.','admission-empty'));
            const list = node('ul',null,'admission-task-list');
            tasks.forEach(task => {
                const line = node('li',null,'admission-task'+(task.completed?' is-done':''));
                const head = node('div',null,'admission-task-header');
                if (canComplete) {
                    const label = node('label',null,'admission-task-check');
                    const checkbox = node('input');
                    checkbox.type = 'checkbox';
                    checkbox.checked = task.completed === true;
                    checkbox.setAttribute('aria-label','Marcar como '+(checkbox.checked?'pendiente':'realizada')+
                        ': '+String(task.text).slice(0,80));
                    checkbox.addEventListener('change',async () => {
                        const expected = task.completed === true;
                        checkbox.checked = !expected;
                        if (busy || confirming || selected !== appointmentId) return;
                        busy = true;
                        checkbox.disabled = true;
                        try {
                            await request(board.dataset.base+'/'+appointmentId+'/tasks/'+task.id+'/completion',{
                                method:'PATCH',body:JSON.stringify({expected_completed:expected,completed:!expected})
                            });
                            busy = false;
                            await select(appointmentId);
                            notice('Estado de la tarea actualizado.');
                        } catch(error) {
                            busy = false;
                            checkbox.checked = expected;
                            notice(error.message);
                            await select(appointmentId);
                        }
                    });
                    append(label,checkbox,node('span',task.completed?'Realizado':'Pendiente'));
                    head.appendChild(label);
                } else {
                    head.appendChild(tag(task.completed?'Realizado':'Pendiente'));
                }
                line.appendChild(head);
                line.appendChild(node('p',task.text,'admission-task-text'));
                if (canManage) {
                    const audit = node('small','Registrado por '+(task.created_by||'Administración'),'admission-task-audit');
                    if (task.completed && task.completed_by) audit.textContent += ' · Completado por '+task.completed_by;
                    line.appendChild(audit);
                }
                list.appendChild(line);
            });
            area.appendChild(list);

            if (canManage) {
                const editor = node('div',null,'admission-task-editor');
                const label = node('label','Nueva observación / tarea');
                const field = node('textarea');field.rows=3;field.maxLength=1000;
                field.placeholder='Ej.: Verificar si ya se realizó la radiografía indicada.';
                field.setAttribute('aria-label','Nueva observación para esta cita');
                const submit = node('button','Agregar observación','admission-btn admission-btn--primary');
                submit.type='button';
                const status=node('p',null,'admission-task-status');status.setAttribute('role','status');
                let requestKey=null;
                field.addEventListener('input',()=>{requestKey=null;});
                submit.addEventListener('click',async()=>{
                    const text=field.value.trim();
                    if (text.length<3 || text.length>1000) {status.textContent='Ingresa entre 3 y 1000 caracteres.';field.focus();return;}
                    if(busy||confirming||selected!==appointmentId)return;
                    requestKey=requestKey||crypto.randomUUID();
                    busy=true;submit.disabled=true;
                    try {
                        await request(board.dataset.base+'/'+appointmentId+'/tasks',{
                            method:'POST',body:JSON.stringify({text,request_key:requestKey})
                        });
                        busy=false;
                        await select(appointmentId);
                        notice('Observación registrada. Queda pendiente hasta que el personal marque su realización.');
                    }catch(error) {
                        busy=false;submit.disabled=false;
                        status.textContent=error.message;
                    }
                });
                append(label,field);
                append(editor,label,submit,status);
                area.appendChild(editor);
            }
            return area;
        }
        function presentationName(parent, name, presentation) {
            const label=node('span',name,'admission-patient-name');
            if(presentation?.strikethrough===true)label.style.textDecoration='line-through';
            parent.appendChild(label);
            for(const badge of presentation?.badges||[]){const chip=tag(badge.etiqueta);chip.style.backgroundColor=badge.fondo;chip.style.color=badge.color;parent.appendChild(chip);}
        }
        function renderRows(payload){
            total=payload.total;page=payload.page;const body=$('rows');body.replaceChildren();
            for(const row of payload.rows){const tr=node('tr');tr.dataset.appointmentId=row.id;if(row.id===selected)tr.className='is-selected';
                const cell=(text,small)=>{const td=node('td',text);if(small)td.appendChild(node('small',small));tr.appendChild(td);return td;};
                cell(row.hora,row.fecha);const patient=cell();presentationName(patient,row.patient,row.presentation);patient.appendChild(node('small','H.C. '+(row.historia||'—')+' · '+(row.documento||'—')));
                cell(row.doctor,row.servicio);const status=cell();if(row.presentation)tr.style.backgroundColor=row.presentation.fondo;append(status,tag(careLabels[row.estado_cita]||row.estado_cita,row.grupo==='desactivadas'?'inactive':''),node('small',bookingLabel(row.estado_agenda)));if(row.grupo==='otros')status.appendChild(tag('Revisar clasificación','review'));
                cell(time(row.tiempos.llegada)+' / '+time(row.tiempos.inicio)+' / '+time(row.tiempos.fin));
                const e=row.economy;cell(money(e.precio)+' / '+(e.fuente==='UNALLOCATED'?'Por conciliar':money(e.pagado))+' / '+(e.fuente==='UNALLOCATED'?'Por conciliar':money(e.saldo)),e.fuente==='LEGACY_SNAPSHOT'?'Abonado histórico sin conciliación':e.credito!=='0.00'?'Crédito '+money(e.credito):e.fuente==='UNALLOCATED'?'Atribución pendiente':row.estado_pagado+' · '+(row.payment_methods.join(', ')||'Sin pago registrado'));
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
        async function select(id,background=false){
            if(busy||confirming)return;
            selected=id;
            if(!background){detail=null;$('detail').replaceChildren(node('p','Consultando cita…','admission-empty'));}
            board.querySelectorAll('tr[data-appointment-id]').forEach(tr=>tr.classList.toggle('is-selected',Number(tr.dataset.appointmentId)===id));
            const url=board.dataset.base+'/'+id+(background&&detail?.revision?'?revision='+encodeURIComponent(detail.revision):'');
            try{
                await detailRequest.run(signal=>request(url,{signal}),payload=>{
                    if(selected!==id||busy||confirming||(background&&editing())||payload.unchanged)return;
                    const scroll=$('detail').scrollTop;
                    const expanded=Array.from($('detail').querySelectorAll('details[open]')).map(n=>n.querySelector('summary')?.textContent);
                    detail=payload;renderDetail(payload);
                    if(background){
                        $('detail').querySelectorAll('details').forEach(n=>{n.open=expanded.includes(n.querySelector('summary')?.textContent);});
                        $('detail').scrollTop=scroll;
                    }
                });
            }catch(error){
                if(background)notice('No se pudo actualizar el detalle. '+error.message);
                else $('detail').replaceChildren(node('p',error.message,'admission-empty'));
            }
        }

        function renderDetail(payload){const root=$('detail'),a=payload.appointment;root.replaceChildren();
            const head=section('CITA '+a.numero),patientName=node('h3');presentationName(patientName,a.patient,a.presentation);append(head,patientName,tag(careLabels[a.estado_cita]||a.estado_cita,a.grupo==='desactivadas'?'inactive':''),node('p',bookingLabel(a.estado_agenda)),node('p',a.fecha+' · '+a.hora),node('small',a.doctor+' · '+a.servicio),node('small',a.sede||'Sede sin registrar; no se ha reasignado'));root.appendChild(head);
            const contact=section('Contacto y responsable');append(contact,node('p',payload.contact.telefono||'Teléfono sin registrar'),node('small',payload.contact.telefono_secundario||''),node('p','Responsable: '+(a.responsable||'—')),node('small','Canal: '+(payload.contact.canal||'—')+' · Medio: '+(payload.contact.medio||'—')));root.appendChild(contact);
            const journey=section('Recorrido de atención'),list=node('ol',null,'admission-journey');for(const[key,label]of Object.entries({llegada:'Llegada',llamado:'Llamado',inicio:'Inicio de atención',fin:'Fin de atención',salida:'Salida física'}))append(list,append(node('li'),node('time',time(a.tiempos[key])),node('span',label)));journey.appendChild(list);root.appendChild(journey);
            const tasksPanel=renderOperationalTasks(payload);if(tasksPanel)root.appendChild(tasksPanel);
            const pricing=section('Situación económica'),dl=node('dl');economic(dl,a.economy);append(pricing,dl,node('small','Fuente: '+a.economy.fuente),node('small','Regla de precio registrada: '+(a.pricing_rule||'Histórica / sin clasificación')));for(const payment of payload.payments||[])pricing.appendChild(node('p',money(payment.amount)+' · '+payment.method+' · '+(payment.recorded_at?new Date(payment.recorded_at).toLocaleString('es-PE',{timeZone:'America/Lima'}):'Sin fecha registrada')));root.appendChild(pricing);
            const actions=section('Operaciones'),buttons=node('div',null,'admission-detail-actions');
            if(a.grupo!=='desactivadas')buttons.appendChild(link(a.estado_cita==='ATENDIDO'?'Consultar cita en Agenda':'Abrir cita · pago / OCR / confirmar',a.agenda_url,true));
            buttons.appendChild(link('Ficha administrativa del paciente',payload.patient_url));
            for(const state of payload.care_actions){const button=node('button',actionLabels[state],'admission-btn');button.type='button';button.addEventListener('click',()=>transition(state));buttons.appendChild(button);}append(actions,buttons,node('small','Cancelación, inasistencia y retiro: utiliza las acciones autorizadas de Agenda. No implican devolución automática.'));root.appendChild(actions);
            const docs=section('Comprobantes de esta cita');if(!payload.documents.length)docs.appendChild(node('p','Sin comprobantes adjuntos al pago.'));for(const d of payload.documents){const p=node('p');p.appendChild(link('Comprobante del pago #'+d.payment_id,d.download_url));docs.appendChild(p);}root.appendChild(docs);
            const operational=section('Documentos operativos de esta cita');
            if(!payload.can_view_operational_documents)operational.appendChild(node('p','Acceso restringido: requiere permiso para documentos privados.'));
            else {if(!payload.operational_documents?.length)operational.appendChild(node('p','Sin documentos operativos vigentes.'));for(const d of payload.operational_documents||[]){const p=node('p');p.appendChild(link(({PRESCRIPTION:'Receta',REPORT:'Informe',ADMINISTRATIVE:'Administrativo'}[d.kind]||'Documento')+' · '+d.label,d.url));operational.appendChild(p);}operational.appendChild(link('Adjuntar o reemplazar desde Agenda',a.agenda_url));}
            root.appendChild(operational);
            const events=section('Actividad registrada'),ul=node('ul',null,'admission-events');for(const e of payload.events)append(ul,append(node('li'),node('span',new Date(e.at).toLocaleString('es-PE',{timeZone:'America/Lima'})+' · '+e.type.replaceAll('_',' ')),node('small',e.actor||'Sin responsable registrado')));if(!payload.events.length)ul.appendChild(node('li','Sin eventos registrados.'));events.appendChild(ul);root.appendChild(events);
            const history=node('details');history.appendChild(node('summary','Historial administrativo · hasta 20 citas visibles'));for(const h of payload.history){const item=node('div',null,'admission-history-item');append(item,node('strong',h.fecha+' · '+h.hora),node('p',h.doctor+' · '+h.servicio),node('small',(careLabels[h.estado_cita]||h.estado_cita)+' · '+bookingLabel(h.estado_agenda)),node('p','Precio '+money(h.economy.precio)+' · Saldo '+(h.economy.fuente==='UNALLOCATED'?'Por conciliar':money(h.economy.saldo))));history.appendChild(item);}if(!payload.history.length)history.appendChild(node('p','Sin otras citas visibles.'));root.appendChild(history);
        }
        async function transition(state){if(busy||!detail)return;const id=detail.appointment.id,expected=detail.appointment.estado_cita;
            if(!window.Swal){notice('No se cargó la confirmación de acciones. Vuelve a abrir el panel.');return;}
            let result;confirming=true;try{result=await window.Swal.fire({title:actionLabels[state],text:state==='SALIDA_REGISTRADA'?'Confirma que el paciente salió físicamente. ATENDIDO y sus pagos se conservan; esto no decide una reevaluación.':'Se registrará la actuación y su responsable. No cambia pagos ni el precio.',...(state==='SALIDA_REGISTRADA'?{input:'textarea',inputLabel:'Motivo o constancia de salida',inputAttributes:{maxlength:'2000'},inputValidator:value=>!String(value||'').trim()?'Registra el motivo o constancia de salida.':undefined}:{}),icon:'question',showCancelButton:true,confirmButtonText:'Confirmar',cancelButtonText:'Volver'});}finally{confirming=false;}if(!result.isConfirmed||busy||selected!==id||detail?.appointment.estado_cita!==expected)return;
            busy=true;board.querySelectorAll('button').forEach(b=>b.disabled=true);try{await request(board.dataset.base+'/'+id+'/care',{method:'POST',body:JSON.stringify({state,expected_state:expected,request_key:crypto.randomUUID(),...(state==='SALIDA_REGISTRADA'?{motivo:String(result.value||'').trim()}:{} )})});busy=false;await load(id);notice('Atención actualizada. Indicadores y saldo consultados nuevamente.');}catch(error){notice(error.message);busy=false;await select(id);}finally{board.querySelectorAll('button').forEach(b=>b.disabled=false);$('prev').disabled=page<=1;$('next').disabled=page*30>=total;}
        }
        async function availability(filters){
            const options=Array.from($('doctor').options).filter(o=>o.value&&o.dataset.active==='1'&&(!filters.doctor_id||o.value===filters.doctor_id)&&(!filters.specialty_id||o.dataset.specialty===filters.specialty_id)).slice(0,6);
            $('availability-note').textContent='Primeros '+options.length+' médicos activos del filtro. Disponibilidad informativa; se revalida al reservar.';
            $('availability').replaceChildren();
            try{await availabilityRequest.run(async signal=>Promise.all(options.map(async option=>{const u=new URL(board.dataset.availability,location.origin);u.searchParams.set('doctor_id',option.value);u.searchParams.set('fecha',filters.fecha);if(filters.site_id)u.searchParams.set('site_id',filters.site_id);try{return {option,result:await request(u.href,{signal})};}catch(error){if(error.name==='AbortError')throw error;return {option,error:true};}})),results=>{for(const entry of results){const card=node('article');card.appendChild(node('h3',entry.option.textContent));if(entry.error)card.appendChild(node('p','No se pudo consultar el horario.'));else{const slots=entry.result.slots||[],free=slots.filter(s=>s.estado==='DISPONIBLE');append(card,node('p',slots.length?free.length+' intervalos libres / '+slots.length+' intervalos del horario':'Sin horario configurado'),node('p',free.length?'Primer intervalo libre: '+free[0].inicio:'Sin intervalos libres'),link('Abrir Agenda',agendaUrl(entry.option.value,filters.fecha)));}$('availability').appendChild(card);}});}catch(error){$('availability-note').textContent='No se pudo consultar disponibilidad. Usa Agenda para verificar horarios.';}
        }
        const editing=()=>confirming||Boolean($('detail').contains(doc.activeElement)&&doc.activeElement?.matches('input,textarea,select,[contenteditable="true"]'));
        async function load(reselect=null,background=false){if(busy||editing())return;
            const snapshot=JSON.stringify(data());if(background&&(doc.hidden||snapshot!==filtersSnapshot))return;
            if(loading){if(!background){pendingLoad={reselect};listRequest.cancel();}return;}
            loading=true;const filters=data();filters.page=page;if(background&&revision)filters.revision=revision;
            if(!background){revision='';clearDetail();availabilityRequest.cancel();$('rows').replaceChildren();$('totals').replaceChildren();$('count').textContent='';$('reservations').textContent='';$('availability').replaceChildren();$('scope').textContent='Consultando los filtros seleccionados…';board.querySelectorAll('[data-kpi]').forEach(el=>el.textContent='—');$('prev').disabled=true;$('next').disabled=true;notice('Consultando conjunto filtrado…');$('create').href=agendaUrl(filters.doctor_id,filters.fecha);}
            try{await listRequest.run(signal=>request(board.dataset.feed,{method:'POST',signal,body:JSON.stringify(filters)}),async payload=>{
                if(snapshot!==JSON.stringify(data())||(background&&(busy||editing()||doc.hidden)))return;
                if(payload.unchanged){notice('');if(reselect&&selected===reselect)await select(reselect,true);return;}revision=payload.revision;filtersSnapshot=snapshot;renderRows(payload);notice('');
                $('scope').textContent='Indicadores y totales: '+payload.total+' citas visibles del '+filters.fecha+' al '+filters.hasta+'. Incluyen todos los resultados de estos filtros, no solo esta página.';
                availability(filters);if(reselect&&(!background?payload.rows.some(a=>a.id===reselect):selected===reselect))await select(reselect,background);
            });}catch(error){notice(background?'Actualización pendiente; se conservan los datos anteriores. '+error.message:error.message);
                if(!background){$('count').textContent='';board.querySelectorAll('[data-kpi]').forEach(el=>el.textContent='—');$('totals').replaceChildren();}
            }finally{loading=false;if(pendingLoad){const next=pendingLoad;pendingLoad=null;load(next.reselect);}}
        }
        form.addEventListener('submit',event=>{event.preventDefault();page=1;load();});$('refresh').addEventListener('click',()=>load(selected));
        $('prev').addEventListener('click',()=>{if(page>1){page--;load();}});$('next').addEventListener('click',()=>{if(page*30<total){page++;load();}});
        $('date').addEventListener('change',()=>{if($('until').value<$('date').value)$('until').value=$('date').value;});
        $('today').addEventListener('click',()=>{$('date').value=$('today').dataset.today;$('until').value=$('date').value;page=1;load();});
        $('specialty').addEventListener('change',()=>{for(const option of $('doctor').options)option.hidden=Boolean(option.value&&$('specialty').value&&option.dataset.specialty!==$('specialty').value);if($('doctor').selectedOptions[0]?.hidden)$('doctor').value='';});
        board.querySelectorAll('[data-group]').forEach(button=>button.addEventListener('click',()=>{$('group').value=button.dataset.group;page=1;load();}));
        const poll=polling({run:()=>load(selected,true),enabled:()=>!doc.hidden&&!busy&&!editing(),alive:()=>board.isConnected});
        doc.addEventListener('visibilitychange',()=>{if(doc.hidden){poll.stop();listRequest.cancel();}else poll.start(true);});
        window.addEventListener('pagehide',()=>{poll.stop();listRequest.cancel();detailRequest.cancel();availabilityRequest.cancel();});
        window.addEventListener('focus',()=>{if(!doc.hidden)poll.start(true);});
        window.addEventListener('pageshow',event=>{if(event.persisted)poll.start(true);});
        load();
        poll.start();
    }
    return {latest,polling,bookingLabel,time,money,readResponse,start};
}));
