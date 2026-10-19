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
    function bookingIndicator(doc,state){
        if(state==='LEGADO')return null;
        const chip=doc.createElement('small');chip.className='admission-booking-indicator';chip.textContent=bookingLabel(state);return chip;
    }
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
        let detailDirty=false, procedureRequest=null, observationDraft='', selectionDraft=null, selectionDraftVersion=null, observationRequest=null, directRequest=null, activeDetailTab='care';
        const updateDirty=()=>{detailDirty=Boolean(observationDraft||selectionDraft);};
        const clearDraft=()=>{observationDraft='';selectionDraft=null;selectionDraftVersion=null;observationRequest=null;detailDirty=false;};
        let page=1,total=0,selected=null,busy=false,confirming=false,detail=null,filtersSnapshot='',revision='',loading=false,pendingLoad=null;
        const node=(tag,text,className)=>{const n=doc.createElement(tag);if(text!==undefined&&text!==null)n.textContent=String(text);if(className)n.className=className;return n;};
        const append=(parent,...children)=>{children.forEach(child=>parent.appendChild(child));return parent;};
        const link=(label,url,primary=false)=>{const a=node('a',label,'admission-btn'+(primary?' admission-btn--primary':''));a.href=url;return a;};
        const tag=(text,kind='')=>node('span',text,'admission-tag'+(kind?' admission-tag--'+kind:''));
        const notice=text=>{$('notice').textContent=text||'';};
        const data=()=>Object.fromEntries(new FormData(form).entries());
        const agendaUrl=(doctorId,fecha)=>{const u=new URL(board.dataset.agenda,location.origin);u.searchParams.set('fecha',fecha);if(doctorId)u.searchParams.set('doctor_id',doctorId);return u.href;};
        async function request(url,options={}){return readResponse(await fetch(url,{credentials:'same-origin',...options,headers:{Accept:'application/json','X-CSRF-TOKEN':doc.querySelector('meta[name="csrf-token"]')?.content||'',...(options.body?{'Content-Type':'application/json'}:{}),...options.headers}}));}
        function clearDetail(){selected=null;detail=null;clearDraft();detailRequest.cancel();$('detail').replaceChildren(node('p','Selecciona una cita para consultar su información y acciones autorizadas.','admission-empty'));}
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
                const field = node('textarea');field.rows=3;field.maxLength=16000;field.value=observationDraft;
                field.placeholder='Ej.: Verificar si ya se realizó la radiografía indicada.';
                field.setAttribute('aria-label','Nueva observación para esta cita');
                const submit = node('button','Agregar observación','admission-btn admission-btn--primary');
                submit.type='button';
                const status=node('p',null,'admission-task-status');status.setAttribute('role','status');
                field.addEventListener('input',()=>{observationDraft=field.value;observationRequest=null;updateDirty();});
                submit.addEventListener('click',async()=>{
                    const text=field.value.replace(/^[\s\u200B\uFEFF]+|[\s\u200B\uFEFF]+$/g,'');
                    if (!text || text.length>16000) {status.textContent=!text?'Escribe una observación antes de guardar.':'La observación supera el máximo técnico de 16000 caracteres.';field.focus();return;}
                    if(busy||confirming||selected!==appointmentId)return;
                    const signature=JSON.stringify([appointmentId,text]);
                    if(observationRequest?.signature!==signature)observationRequest={signature,key:crypto.randomUUID()};
                    busy=true;submit.disabled=true;
                    try {
                        await request(board.dataset.base+'/'+appointmentId+'/tasks',{
                            method:'POST',body:JSON.stringify({text,request_key:observationRequest.key})
                        });
                        observationDraft='';observationRequest=null;updateDirty();busy=false;
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
        async function procedureChange(action, values) {
            if(busy||confirming||!detail)return;
            const id=detail.appointment.id;
            const body={expected_version:action==='selection'?(selectionDraftVersion??detail.procedures.version):detail.procedures.version,...values};
            const signature=JSON.stringify([id,action,body]);
            if(procedureRequest?.signature!==signature)procedureRequest={signature,key:crypto.randomUUID()};
            body.request_key=procedureRequest.key;
            busy=true;
            try {
                await request(board.dataset.base+'/'+id+'/procedures/'+action,{method:'POST',body:JSON.stringify(body)});
                procedureRequest=null;if(action==='selection'){selectionDraft=null;selectionDraftVersion=null;}updateDirty();busy=false;
                await select(id);
                notice('Procedimientos y reevaluación actualizados.');
            }catch(error){busy=false;notice(error.message);}
        }
        async function reviewDecision(action) {
            if(busy||confirming||!detail||!window.Swal)return;
            const id=detail.appointment.id;
            const pending=detail.procedures.items.some(p=>!p.completed);
            confirming=true;let answer;
            try {answer=await window.Swal.fire({title:action==='send'?'Enviar a reevaluación':'Retirar de reevaluación',
                text:'Se registrará tu decisión dentro de la misma cita. Los procedimientos pendientes se conservan.',
                input:'textarea',inputLabel:action==='withdraw'||pending?'Motivo obligatorio':'Motivo (opcional)',
                inputAttributes:{maxlength:'500'},inputValidator:value=>(action==='withdraw'||pending)&&!String(value||'').trim()?'Indica un motivo.':undefined,
                showCancelButton:true,confirmButtonText:'Confirmar decisión',cancelButtonText:'Volver',focusCancel:true});
            }finally{confirming=false;}
            if(answer?.isConfirmed&&selected===id)await procedureChange(action,{confirmed:true,reason:String(answer.value||'').trim()});
        }
        async function toggleDirect(){
            if(busy||confirming||!detail?.reevaluation?.direct?.can_update||!window.Swal)return;
            const id=detail.appointment.id,current=detail.reevaluation.direct;
            confirming=true;let answer;
            try{answer=await window.Swal.fire({title:current.marked?'Retirar marca manual directa':'Marcar reevaluación manual directa',
                text:current.marked?'Solo se desactiva esta marca. Otros orígenes válidos seguirán activos.':'Retorno al médico dentro de la misma cita ATENDIDO. Se registrará quién lo autoriza, sin crear cita ni cobro.',
                showCancelButton:true,confirmButtonText:'Confirmar marca',cancelButtonText:'Volver',focusCancel:true});}finally{confirming=false;}
            if(!answer?.isConfirmed||selected!==id)return;
            const body={marked:!current.marked,expected_marked:current.marked,expected_version:current.version};
            const signature=JSON.stringify([id,body]);if(directRequest?.signature!==signature)directRequest={signature,key:crypto.randomUUID()};
            body.request_key=directRequest.key;busy=true;
            try{await request(board.dataset.base+'/'+id+'/manual-reevaluation',{method:'PATCH',body:JSON.stringify(body)});directRequest=null;busy=false;await select(id);notice('Marca directa actualizada; los demás orígenes y el historial se conservan.');}
            catch(error){busy=false;notice(error.message);}
        }
        function renderProcedures(payload) {
            const data=payload.procedures;if(!data)return null;
            const area=section('Reevaluación y procedimientos');area.classList.add('clarity-mask');
            const requested=data.items.length,completed=data.items.filter(p=>p.completed).length,pending=data.items.filter(p=>!p.completed);
            const unified=payload.reevaluation||{ready:data.review.status==='READY',origins:[]},direct=unified.direct;
            const labels={READY:'Listo para reevaluación',NOT_READY:requested?'Pendiente de procedimientos':'Sin procedimientos solicitados',WITHDRAWN:'Reevaluación retirada',SUSPENDED:'Autorización manual suspendida',INACTIVE:'Reevaluación inactiva'};
            const status=node('div',null,'admission-procedure-status');append(status,node('strong',unified.ready?'Listo para reevaluación':labels[data.review.status]||data.review.status),tag(requested?completed+' de '+requested+' realizados':'Sin procedimientos',unified.ready?'ready':'neutral'));area.appendChild(status);
            if(direct&&(direct.version||direct.can_update)){
                const box=node('div',null,'admission-direct-return');
                box.appendChild(node('strong',direct.active?'Origen: marca manual directa de cita ATENDIDO':direct.marked?'Marca directa conservada · inactiva':'Retorno manual directo de cita ATENDIDO'));
                if(direct.actor)box.appendChild(node('small',(direct.marked?'Autorizó: ':'Último cambio: ')+direct.actor.name+' · '+new Date(direct.at).toLocaleString('es-PE',{timeZone:'America/Lima'})));
                if(direct.can_update){const button=node('button',direct.marked?'Retirar marca manual directa':'Marcar reevaluación manual directa','admission-btn');button.type='button';button.dataset.directReevaluation='true';button.addEventListener('click',toggleDirect);box.appendChild(button);}area.appendChild(box);
            }
            if(requested){const meter=node('progress',null,'admission-procedure-progress');meter.max=requested;meter.value=completed;meter.setAttribute('aria-label','Progreso de procedimientos');area.appendChild(meter);}
            let explanation=!requested?'El administrador todavía no ha confirmado procedimientos para esta cita.':completed+' de '+requested+' procedimientos realizados.';
            if(pending.length)explanation+=' Falta '+pending.map(p=>p.label).join(', ')+'.';
            if(data.review.status==='READY')explanation+=data.review.origin==='MANUAL'?' Envío manual autorizado; los pendientes se conservan.':' Paciente listo para volver al médico.';
            else if(data.can_send&&completed)explanation+=' Puedes enviarlo manualmente si corresponde.';
            else if(requested&&!completed)explanation+=' Registra al menos uno realizado antes de considerar un envío manual.';
            if(direct?.active)explanation+=' Retorno manual directo autorizado; no acredita procedimientos.';
            area.appendChild(node('p',explanation,'admission-procedure-explanation'));
            if(data.review.status==='WITHDRAWN'){
                const withdrawal=node('div',null,'admission-manual-authorization');append(withdrawal,node('strong','Envío por procedimientos retirado por '+(data.review.decision_by?.name||'Usuario no disponible')),node('p',data.review.reason||'Sin motivo registrado'));area.appendChild(withdrawal);
            }else if(data.review.origin==='MANUAL'){
                const auth=node('div',null,'admission-manual-authorization');auth.appendChild(node('strong','Autorización por procedimientos parciales · '+(data.review.authorized_by?.name||'Usuario no disponible')));
                auth.appendChild(node('p',data.review.reason||'Sin motivo adicional'));auth.appendChild(node('small','Ingreso registrado: '+time(data.review.ready_at)));area.appendChild(auth);
            }else if(data.review.origin==='AUTOMATIC'&&data.review.ready_at){area.appendChild(node('small',data.review.status==='READY'?'Ingreso automático · '+time(data.review.ready_at):'Ingreso automático anterior; la realización se recalculó.'));}
            const list=node('ul',null,'admission-procedure-items');
            for(const p of data.items){const line=node('li'),label=node('label');
                if(data.can_complete){const box=node('input');box.type='checkbox';box.checked=p.completed;box.dataset.procedureCompleted=p.key;box.setAttribute('aria-label',p.label+' realizado');box.addEventListener('change',()=>{box.checked=p.completed;procedureChange('completion',{procedure:p.key,completed:!p.completed});});label.appendChild(box);}
                append(label,node('span',p.label));append(line,label,node('small',p.completed?'Realizado':'Pendiente'));list.appendChild(line);
            }
            area.insertBefore(list,area.querySelector('.admission-procedure-explanation'));
            const directPanel=area.querySelector('.admission-direct-return');if(data.can_complete&&directPanel){const meter=area.querySelector('progress');if(meter)area.insertBefore(meter,directPanel);area.insertBefore(list,directPanel);}
            const decisions=node('div',null,'admission-detail-actions');
            if(data.can_send&&data.review.status!=='READY'){const send=node('button','Enviar a reevaluación','admission-btn');send.type='button';send.addEventListener('click',()=>reviewDecision('send'));decisions.appendChild(send);}
            if(data.can_withdraw){const withdraw=node('button','Retirar envío por procedimientos','admission-btn');withdraw.type='button';withdraw.addEventListener('click',()=>reviewDecision('withdraw'));decisions.appendChild(withdraw);}
            area.appendChild(decisions);
            if(data.can_select){
                const editor=node('details',null,'admission-procedure-editor');editor.open=!requested||selectionDraft!==null;
                editor.appendChild(node('summary','Editar solicitud de procedimientos'));
                const picker=node('fieldset',null,'admission-procedure-picker');picker.appendChild(node('legend','Solicitud del administrador'));
                const confirmed=new Set(data.items.map(p=>p.key)),selected=new Set(selectionDraft||confirmed);
                const draftStatus=node('small',selectionDraft?'Selección sin guardar.':'Marcar casillas no guarda. Confirma expresamente.');
                for(const[key,label]of Object.entries(data.catalog)){
                    const line=node('label',null,'admission-task-check'),box=node('input');box.type='checkbox';box.checked=selected.has(key);box.dataset.procedureSelection=key;
                    box.addEventListener('change',()=>{if(box.checked)selected.add(key);else selected.delete(key);selectionDraftVersion??=data.version;selectionDraft=new Set(selected);updateDirty();draftStatus.textContent='Selección sin guardar.';});
                    append(line,box,node('span',label));picker.appendChild(line);
                }
                const confirm=node('button','Confirmar selección','admission-btn admission-btn--primary');confirm.type='button';confirm.addEventListener('click',()=>procedureChange('selection',{procedures:Array.from(selected)}));
                append(picker,confirm,draftStatus);editor.appendChild(picker);area.appendChild(editor);
            }
            if(unified.history?.length){
                const audit=node('details',null,'admission-reevaluation-audit'),title=node('summary','Historial de reevaluación'),entries=node('div');
                append(audit,title,entries);let auditPage=unified.history_meta?.page||1,loaded=0;
                const names={REEVALUACION_MANUAL_MARCADA:'Marca directa autorizada',REEVALUACION_MANUAL_DESMARCADA:'Marca directa retirada',SEND:'Envío por procedimientos autorizado',WITHDRAW:'Envío por procedimientos retirado',SELECTION:'Solicitud confirmada',COMPLETION:'Realización corregida'};
                const states={READY:'Listo',NOT_READY:'Pendiente',SUSPENDED:'Autorización suspendida',WITHDRAWN:'Envío retirado'};
                const addEvents=events=>{for(const event of events){const line=node('p',(names[event.action]||event.action)+' · '+event.actor.name+' · '+new Date(event.at).toLocaleString('es-PE',{timeZone:'America/Lima'}));line.dataset.auditEvent=event.id;
                    if(event.source==='PROCEDURES')line.appendChild(node('small',event.completed+' de '+event.requested+' · '+(states[event.status]||event.status)+(event.reason&&['SEND','WITHDRAW'].includes(event.action)?' · '+event.reason:'')));entries.appendChild(line);loaded++;}title.textContent='Historial de reevaluación · '+loaded+' movimientos';};
                addEvents(unified.history);
                if(unified.history_meta?.has_more){const more=node('button','Ver movimientos anteriores','admission-btn'),status=node('small');more.type='button';
                    more.addEventListener('click',async()=>{const id=payload.appointment.id;more.disabled=true;status.textContent='';
                        try{const query=new URLSearchParams({page:auditPage+1,...unified.history_meta.cursor});const result=await request(board.dataset.base+'/'+id+'/reevaluation-history?'+query);if(selected!==id||!audit.isConnected)return;addEvents(result.rows);auditPage=result.page;more.hidden=!result.has_more;}
                        catch(error){status.textContent=error.message;}finally{more.disabled=false;}});append(audit,more,status);}
                area.appendChild(audit);
            }
            return area;
        }
        async function reverseCare(offer) {
            if(busy||confirming||!detail||!window.Swal)return;
            const id=detail.appointment.id;confirming=true;let answer;
            try{answer=await window.Swal.fire({title:'Retroceder estado',text:'Restaurar '+(careLabels[offer.to]||offer.to)+'. Se conservará el historial.',
                input:'textarea',inputLabel:'Motivo de corrección',inputAttributes:{maxlength:'500'},
                inputValidator:value=>String(value||'').trim().length<3?'Indica el motivo de corrección.':undefined,
                showCancelButton:true,confirmButtonText:'Confirmar corrección',cancelButtonText:'Volver',focusCancel:true});}finally{confirming=false;}
            if(!answer?.isConfirmed||selected!==id)return;
            busy=true;
            try{await request(board.dataset.base+'/'+id+'/care-reversal',{method:'POST',body:JSON.stringify({expected_event_id:offer.event_id,
                expected_state:offer.from,request_key:crypto.randomUUID(),motivo:String(answer.value).trim()})});
                busy=false;await select(id);notice('Estado corregido; historial conservado.');
            }catch(error){busy=false;notice(error.message);}
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
                const tones={LISTO_REEVALUACION:'#ce7a30',PRESENTE:'#32948c',PAGO_REAL:'#bd9c39',DESACTIVADA:'#bd5963',REEMBOLSO_SOLICITADO:'#bd5963',SALIDA_REGISTRADA:'#6f9c97',ATENDIDO:'#74dccb'};
                tr.style.setProperty('--ad-status-color',tones[row.presentation?.clave]||'#dce8e9');
                const cell=(text,small)=>{const td=node('td',text);if(small)td.appendChild(node('small',small));tr.appendChild(td);return td;};
                const date=cell();append(date,node('strong',row.hora),node('small',row.fecha.slice(5).split('-').reverse().join('/')));
                const patient=cell();presentationName(patient,row.patient,{...row.presentation,badges:(row.presentation?.badges||[]).filter(b=>['HERIDA','POSTOPERATORIO','CONCILIAR_PAGO'].includes(b.clave))});
                patient.appendChild(node('small','H.C. '+(row.historia||'—')+' · '+(row.documento||'—')));patient.appendChild(node('small',row.motivo_resumen||'Motivo: no registrado','admission-reason-summary'));
                cell(row.doctor,row.servicio);const status=cell();append(status,tag(careLabels[row.estado_cita]||row.estado_cita,row.estado_cita==='ATENDIDO'?'attended':row.grupo==='desactivadas'?'inactive':'neutral'));const booking=bookingIndicator(doc,row.estado_agenda);if(booking)status.appendChild(booking);
                const procedures=cell(),progress=row.procedure_progress||{requested:0,completed:0,status:'NOT_READY'},ready=row.reevaluation?.ready??progress.status==='READY';
                if(progress.requested)procedures.appendChild(node('span',progress.completed+' de '+progress.requested+' realizados'));
                procedures.appendChild(tag(ready?'Listo para reevaluación':progress.status==='NOT_READY'?(progress.requested?'Pendientes':'Sin solicitud'):({WITHDRAWN:'Retirado',SUSPENDED:'Suspendido',INACTIVE:'Inactivo'}[progress.status]||progress.status),ready?'ready':'neutral'));
                const button=node('button','Ver','admission-btn');button.type='button';button.setAttribute('aria-label','Ver cita de '+row.patient+' a las '+row.hora);button.addEventListener('click',()=>select(row.id));cell().appendChild(button);body.appendChild(tr);
            }
            if(!payload.rows.length){const tr=node('tr'),td=node('td','No hay citas visibles que coincidan con estos filtros.');td.colSpan=6;tr.appendChild(td);body.appendChild(tr);}
            for(const [key,count]of Object.entries(payload.kpis)){const target=board.querySelector('[data-kpi="'+key+'"]');if(target)target.textContent=count;}
            $('count').textContent='· '+payload.total;
            $('reservations').textContent=payload.kpis.reservas+' reservas pendientes · '+payload.kpis.otros+' para revisar';
            $('page').textContent='Página '+page+' de '+Math.max(1,Math.ceil(total/30));$('prev').disabled=page<=1;$('next').disabled=page*30>=total;
            $('paid-total').textContent=money(payload.totals.pagos_reales);$('balance-total').textContent=money(payload.totals.saldo);
            const totals=$('totals');totals.replaceChildren();const labels={precio:'Precio neto',pagos_reales:'Pagos acreditados',pagado_historico:'Abonado histórico',credito:'Crédito aplicado',saldo:'Saldo atribuido'};
            for(const[key,label]of Object.entries(labels))totals.appendChild(description(label,money(payload.totals[key])));
            if(payload.totals.requieren_revision)totals.appendChild(description('Atribución pendiente',payload.totals.requieren_revision+' citas'));
        }
        async function select(id,background=false){
            if(busy||confirming)return;
            if(background && detailDirty)return;
            if(id!==selected&&detailDirty){
                if(!window.Swal){notice('Hay cambios sin guardar. Confírmalos antes de cambiar de paciente.');return;}
                confirming=true;let answer;try{answer=await window.Swal.fire({title:'Cambiar de paciente',text:'Hay cambios sin guardar en la cita abierta.',showCancelButton:true,confirmButtonText:'Descartar y abrir',cancelButtonText:'Conservar cambios',focusCancel:true});}finally{confirming=false;}
                if(!answer?.isConfirmed)return;clearDraft();
            }
            if(id!==selected){clearDraft();activeDetailTab='care';}
            selected=id;board.dataset.mobileDetail='true';
            if(!background){detail=null;$('detail').replaceChildren(node('p','Consultando cita…','admission-empty'));}
            board.querySelectorAll('tr[data-appointment-id]').forEach(tr=>tr.classList.toggle('is-selected',Number(tr.dataset.appointmentId)===id));
            const url=board.dataset.base+'/'+id+(background&&detail?.revision?'?revision='+encodeURIComponent(detail.revision):'');
            try{
                await detailRequest.run(signal=>request(url,{signal}),payload=>{
                    if(selected!==id||busy||confirming||(background&&editing())||payload.unchanged)return;
                    const scroll=$('detail').scrollTop;
                    const expanded=Array.from($('detail').querySelectorAll('details[open]')).map(n=>n.querySelector('summary')?.textContent);
                    detail=payload;updateDirty();renderDetail(payload);
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
            const head=section('CITA '+a.numero),patientName=node('h3');presentationName(patientName,a.patient,{...a.presentation,badges:[]});
            append(head,patientName,tag(careLabels[a.estado_cita]||a.estado_cita,a.estado_cita==='ATENDIDO'?'attended':a.grupo==='desactivadas'?'inactive':'neutral'),node('small',a.fecha+' · '+a.hora+' · H.C. '+(a.historia||'—')+' · '+(a.documento||'—')),node('small',a.doctor+' · '+a.servicio));
            const reason=node('div',null,'admission-consultation-reason');append(reason,node('strong','MOTIVO DE CONSULTA'),node('p',a.motivo_consulta?.trim()||'No registrado'));head.appendChild(reason);root.appendChild(head);
            const actions=node('div',null,'admission-care-actions');
            for(const state of payload.care_actions){const button=node('button',actionLabels[state],'admission-btn'+(state==='EN_ATENCION'||state==='EN_ESPERA'?' admission-btn--primary':''));button.type='button';button.addEventListener('click',()=>transition(state));actions.appendChild(button);}
            if(payload.care_reversal){const reverse=node('button','Retroceder estado','admission-btn');reverse.type='button';reverse.addEventListener('click',()=>reverseCare(payload.care_reversal));actions.appendChild(reverse);}root.appendChild(actions);
            const tabs=node('div',null,'admission-detail-tabs');tabs.setAttribute('role','tablist');tabs.setAttribute('aria-label','Información de la cita');
            const panels={};
            for(const[key,label]of Object.entries({care:'Atención',notes:'Observaciones',money:'Pagos y saldo',history:'Documentos e historial'})){
                const button=node('button',label);button.type='button';button.id='admission-detail-tab-'+key;button.dataset.detailTab=key;button.setAttribute('role','tab');button.setAttribute('aria-controls','admission-detail-panel-'+key);
                const panel=node('div');panel.id='admission-detail-panel-'+key;panel.setAttribute('role','tabpanel');panel.setAttribute('aria-labelledby',button.id);panels[key]=panel;
                button.addEventListener('click',()=>activateDetailTab(key));button.addEventListener('keydown',event=>{const keys=Object.keys(panels);const next=tabKey(event,keys,key);if(next){activateDetailTab(next);tabs.querySelector('[data-detail-tab='+next+']').focus();}});tabs.appendChild(button);
            }
            root.appendChild(tabs);for(const panel of Object.values(panels))root.appendChild(panel);
            function activateDetailTab(key){activeDetailTab=key;for(const[k,panel]of Object.entries(panels)){panel.hidden=k!==key;const button=tabs.querySelector('[data-detail-tab='+k+']');button.setAttribute('aria-selected',String(k===key));button.tabIndex=k===key?0:-1;}}
            const proceduresPanel=renderProcedures(payload);if(proceduresPanel)panels.care.appendChild(proceduresPanel);
            const journey=section('Recorrido de atención'),journeyList=node('ol',null,'admission-journey');for(const[key,label]of Object.entries({llegada:'Llegada',llamado:'Llamado',inicio:'Inicio',fin:'Atendido',salida:'Salida física'}))append(journeyList,append(node('li'),node('time',time(a.tiempos[key])),node('span',label)));journey.appendChild(journeyList);panels.care.appendChild(journey);
            panels.care.appendChild(link('Abrir en Agenda operativa',a.agenda_url));
            const tasks=renderOperationalTasks(payload);if(tasks)panels.notes.appendChild(tasks);else panels.notes.appendChild(node('p','No tienes acceso a observaciones operativas.','admission-empty'));
            const pricing=section('Situación económica'),dl=node('dl');economic(dl,a.economy);append(pricing,dl,node('small','Fuente: '+a.economy.fuente),node('small','Regla de precio: '+(a.pricing_rule||'Histórica / sin clasificación')));for(const payment of payload.payments||[])pricing.appendChild(node('p',money(payment.amount)+' · '+payment.method+' · '+(payment.recorded_at?new Date(payment.recorded_at).toLocaleString('es-PE',{timeZone:'America/Lima'}):'Sin fecha registrada')));pricing.appendChild(link('Registrar pago / OCR en Agenda',a.agenda_url,true));panels.money.appendChild(pricing);
            const contact=section('Ficha y contacto');append(contact,node('p',payload.contact.telefono||'Teléfono sin registrar'),node('small',payload.contact.telefono_secundario||''),node('p','Responsable: '+(a.responsable||'—')),node('small','Sede: '+(a.sede||'Sin registrar')+' · Canal: '+(payload.contact.canal||'—')+' · Medio: '+(payload.contact.medio||'—')),link('Ficha administrativa del paciente',payload.patient_url));panels.history.appendChild(contact);
            const docs=section('Comprobantes de pago');if(!payload.documents.length)docs.appendChild(node('p','Sin comprobantes adjuntos.'));for(const d of payload.documents)docs.appendChild(link('Comprobante del pago #'+d.payment_id,d.download_url));panels.history.appendChild(docs);
            const operational=section('Documentos operativos');if(!payload.can_view_operational_documents)operational.appendChild(node('p','Acceso restringido a documentos privados.'));else {if(!payload.operational_documents?.length)operational.appendChild(node('p','Sin documentos operativos vigentes.'));for(const d of payload.operational_documents||[])operational.appendChild(link(({PRESCRIPTION:'Receta',REPORT:'Informe',ADMINISTRATIVE:'Administrativo'}[d.kind]||'Documento')+' · '+d.label,d.url));operational.appendChild(link('Adjuntar o reemplazar desde Agenda',a.agenda_url));}panels.history.appendChild(operational);
            const events=section('Actividad registrada'),ul=node('ul',null,'admission-events');for(const e of payload.events)append(ul,append(node('li'),node('span',new Date(e.at).toLocaleString('es-PE',{timeZone:'America/Lima'})+' · '+e.type.replaceAll('_',' ')),node('small',e.actor||'Sin responsable registrado')));if(!payload.events.length)ul.appendChild(node('li','Sin eventos registrados.'));events.appendChild(ul);panels.history.appendChild(events);
            const history=section('Historial administrativo · hasta 20 citas visibles');for(const item of payload.history){const article=node('article',null,'admission-history-item');append(article,node('strong',item.fecha+' · '+item.hora),node('p',item.doctor+' · '+item.servicio),node('small',(careLabels[item.estado_cita]||item.estado_cita)+' · '+bookingLabel(item.estado_agenda)));const previous=node('dl');economic(previous,item.economy);article.appendChild(previous);history.appendChild(article);}if(!payload.history.length)history.appendChild(node('p','Sin otras citas visibles.'));panels.history.appendChild(history);
            activateDetailTab(activeDetailTab);resizeWorkspace();
        }
        function tabKey(event,keys,current){let index=keys.indexOf(current);if(event.key==='ArrowRight')index=(index+1)%keys.length;else if(event.key==='ArrowLeft')index=(index+keys.length-1)%keys.length;else if(event.key==='Home')index=0;else if(event.key==='End')index=keys.length-1;else return null;event.preventDefault();return keys[index];}
        function resizeWorkspace(){const area=board.querySelector('.admission-workspace');if(area&&window.innerWidth>1024)board.style.setProperty('--ad-workspace-height',Math.max(340,window.innerHeight-area.getBoundingClientRect().top-20)+'px');}
        async function transition(state){if(busy||!detail)return;const id=detail.appointment.id,expected=detail.appointment.estado_cita;
            if(!window.Swal){notice('No se cargó la confirmación de acciones. Vuelve a abrir el panel.');return;}
            let result;confirming=true;try{result=await window.Swal.fire({title:actionLabels[state],text:state==='SALIDA_REGISTRADA'?'Confirma que el paciente salió físicamente. ATENDIDO y sus pagos se conservan; esto no decide una reevaluación.':'Se registrará la actuación y su responsable. No cambia pagos ni el precio.',...(state==='SALIDA_REGISTRADA'?{input:'textarea',inputLabel:'Motivo o constancia de salida',inputAttributes:{maxlength:'2000'},inputValidator:value=>!String(value||'').trim()?'Registra el motivo o constancia de salida.':undefined}:{}),icon:'question',showCancelButton:true,confirmButtonText:'Confirmar',cancelButtonText:'Volver'});}finally{confirming=false;}if(!result.isConfirmed||busy||selected!==id||detail?.appointment.estado_cita!==expected)return;
            busy=true;board.querySelectorAll('button').forEach(b=>b.disabled=true);try{await request(board.dataset.base+'/'+id+'/care',{method:'POST',body:JSON.stringify({state,expected_state:expected,request_key:crypto.randomUUID(),...(state==='SALIDA_REGISTRADA'?{motivo:String(result.value||'').trim()}:{} )})});busy=false;await load(id);notice('Atención actualizada. Indicadores y saldo consultados nuevamente.');}catch(error){notice(error.message);busy=false;await select(id);}finally{board.querySelectorAll('button').forEach(b=>b.disabled=false);$('prev').disabled=page<=1;$('next').disabled=page*30>=total;}
        }
        async function availability(filters){
            if(!$('availability-panel').open)return;
            const options=Array.from($('doctor').options).filter(o=>o.value&&o.dataset.active==='1'&&(!filters.doctor_id||o.value===filters.doctor_id)&&(!filters.specialty_id||o.dataset.specialty===filters.specialty_id)).slice(0,6);
            $('availability-note').textContent='Primeros '+options.length+' médicos activos del filtro. Disponibilidad informativa; se revalida al reservar.';
            $('availability').replaceChildren();
            try{await availabilityRequest.run(async signal=>Promise.all(options.map(async option=>{const u=new URL(board.dataset.availability,location.origin);u.searchParams.set('doctor_id',option.value);u.searchParams.set('fecha',filters.fecha);if(filters.site_id)u.searchParams.set('site_id',filters.site_id);try{return {option,result:await request(u.href,{signal})};}catch(error){if(error.name==='AbortError')throw error;return {option,error:true};}})),results=>{for(const entry of results){const card=node('article');card.appendChild(node('h3',entry.option.textContent));if(entry.error)card.appendChild(node('p','No se pudo consultar el horario.'));else{const slots=entry.result.slots||[],free=slots.filter(s=>s.estado==='DISPONIBLE');append(card,node('p',slots.length?free.length+' intervalos libres / '+slots.length+' intervalos del horario':'Sin horario configurado'),node('p',free.length?'Primer intervalo libre: '+free[0].inicio:'Sin intervalos libres'),link('Abrir Agenda',agendaUrl(entry.option.value,filters.fecha)));}$('availability').appendChild(card);}});}catch(error){$('availability-note').textContent='No se pudo consultar disponibilidad. Usa Agenda para verificar horarios.';}
        }

        const editing=()=>confirming||detailDirty||Boolean($('detail').querySelector?.('.admission-reevaluation-audit[open]'))||Boolean($('detail').contains(doc.activeElement)&&doc.activeElement?.matches('input,textarea,select,[contenteditable="true"]'));
        async function load(reselect=null,background=false){if(busy||confirming||(background&&editing()))return;
            const snapshot=JSON.stringify(data());if(background&&(doc.hidden||snapshot!==filtersSnapshot))return;
            if(loading){if(!background){pendingLoad={reselect};listRequest.cancel();}return;}
            loading=true;const filters=data();filters.page=page;if(background&&revision)filters.revision=revision;
            if(!background){revision='';if(!detailDirty)clearDetail();availabilityRequest.cancel();$('rows').replaceChildren();$('totals').replaceChildren();$('count').textContent='';$('reservations').textContent='';$('availability').replaceChildren();$('scope').textContent='Consultando los filtros seleccionados…';board.querySelectorAll('[data-kpi]').forEach(el=>el.textContent='—');$('prev').disabled=true;$('next').disabled=true;notice('Consultando conjunto filtrado…');$('create').href=agendaUrl(filters.doctor_id,filters.fecha);}
            try{await listRequest.run(signal=>request(board.dataset.feed,{method:'POST',signal,body:JSON.stringify(filters)}),async payload=>{
                if(snapshot!==JSON.stringify(data())||(background&&(busy||editing()||doc.hidden)))return;
                if(payload.unchanged){notice('');if(reselect&&selected===reselect)await select(reselect,true);return;}revision=payload.revision;filtersSnapshot=snapshot;renderRows(payload);notice('');
                $('scope').textContent='Indicadores y totales: '+payload.total+' citas visibles del '+filters.fecha+' al '+filters.hasta+'. Incluyen todos los resultados de estos filtros, no solo esta página.';
                availability(filters);if(reselect&&!detailDirty&&(!background?payload.rows.some(a=>a.id===reselect):selected===reselect))await select(reselect,background);
                if(detailDirty&&!background)notice('Filtros aplicados. Se conserva el borrador del paciente abierto.');updateFilterState();resizeWorkspace();
            });}catch(error){notice(background?'Actualización pendiente; se conservan los datos anteriores. '+error.message:error.message);
                if(!background){$('count').textContent='';board.querySelectorAll('[data-kpi]').forEach(el=>el.textContent='—');$('totals').replaceChildren();}
            }finally{loading=false;if(pendingLoad){const next=pendingLoad;pendingLoad=null;load(next.reselect);}}
        }
        form.addEventListener('submit',event=>{event.preventDefault();page=1;load();});$('refresh').addEventListener('click',async()=>{
            if(detailDirty){
                if(!window.Swal){notice('Hay cambios sin guardar. Confírmalos antes de actualizar.');return;}
                confirming=true;let answer;
                try{answer=await window.Swal.fire({title:'Actualizar detalle',text:'Hay cambios sin guardar. Descártalos únicamente si quieres consultar la versión actual.',
                    showCancelButton:true,confirmButtonText:'Descartar y actualizar',cancelButtonText:'Conservar cambios',focusCancel:true});}finally{confirming=false;}
                if(!answer?.isConfirmed)return;
                clearDraft();
            }
            load(selected);
        });
        $('prev').addEventListener('click',()=>{if(page>1){page--;load();}});$('next').addEventListener('click',()=>{if(page*30<total){page++;load();}});
        $('date').addEventListener('change',()=>{$('until').value=$('date').value;});
        $('today').addEventListener('click',()=>{$('date').value=$('today').dataset.today;$('until').value=$('date').value;page=1;load();});
        $('specialty').addEventListener('change',()=>{for(const option of $('doctor').options)option.hidden=Boolean(option.value&&$('specialty').value&&option.dataset.specialty!==$('specialty').value);if($('doctor').selectedOptions[0]?.hidden)$('doctor').value='';refreshPickers();});
        board.querySelectorAll('[data-group]').forEach(button=>button.addEventListener('click',()=>{$('group').value=button.dataset.group;page=1;load();}));
        function refreshPickers(){if(window.jQuery?.fn?.selectpicker)window.jQuery(form).find('select').each(function(){if(window.jQuery(this).data('selectpicker'))window.jQuery(this).selectpicker('refresh');});}
        function updateFilterState(){const filters=data();const advanced=['site_id','specialty_id','estado','grupo','visual'].filter(k=>filters[k]).length+(filters.hasta!==filters.fecha?1:0);$('more-count').textContent=advanced?String(advanced):'';board.querySelectorAll('[data-group]').forEach(button=>{const active=button.dataset.group===(filters.grupo||'')&&!filters.only_reevaluations;button.classList.toggle('is-active',active);button.setAttribute('aria-pressed',String(active));});const review=board.querySelector('[data-review-only]');review.classList.toggle('is-active',Boolean(filters.only_reevaluations));review.setAttribute('aria-pressed',String(Boolean(filters.only_reevaluations)));}
        $('reset').addEventListener('click',()=>{form.reset();$('until').value=$('date').value;for(const option of $('doctor').options)option.hidden=false;refreshPickers();page=1;load();});
        board.querySelector('[data-review-only]').addEventListener('click',()=>{$('only-reviews').checked=!$('only-reviews').checked;$('group').value='';page=1;load();});
        $('only-reviews').addEventListener('change',()=>{page=1;load();});
        $('availability-panel').addEventListener('toggle',()=>{if($('availability-panel').open)availability(data());resizeWorkspace();});
        $('more-filters').addEventListener('toggle',resizeWorkspace);board.querySelector('.admission-financial-summary').addEventListener('toggle',resizeWorkspace);
        $('back-list').addEventListener('click',()=>{board.dataset.mobileDetail='false';$('patients-tab').focus();});
        function listTab(key){for(const name of ['patients','queue']){const active=name===key;$(''+name+'-tab').setAttribute('aria-selected',String(active));$(''+name+'-tab').tabIndex=active?0:-1;$(''+name+'-panel').hidden=!active;}resizeWorkspace();}
        for(const key of ['patients','queue']){$(key+'-tab').addEventListener('click',()=>listTab(key));$(key+'-tab').addEventListener('keydown',event=>{const next=tabKey(event,['patients','queue'],key);if(next){listTab(next);$(next+'-tab').focus();}});}
        doc.addEventListener('operational-queue-select',event=>select(event.detail.id));
        doc.addEventListener('operational-queue-updated',event=>{$('queue-count').textContent=String(event.detail?.active??0);});
        window.addEventListener('resize',resizeWorkspace);if(window.ResizeObserver)new ResizeObserver(resizeWorkspace).observe(board.querySelector('.admission-commandbar'));resizeWorkspace();
        const poll=polling({run:()=>load(selected,true),enabled:()=>!doc.hidden&&!busy&&!editing(),alive:()=>board.isConnected});
        doc.addEventListener('visibilitychange',()=>{if(doc.hidden){poll.stop();listRequest.cancel();}else poll.start(true);});
        window.addEventListener('pagehide',()=>{poll.stop();listRequest.cancel();detailRequest.cancel();availabilityRequest.cancel();});
        window.addEventListener('focus',()=>{if(!doc.hidden)poll.start(true);});
        window.addEventListener('pageshow',event=>{if(event.persisted)poll.start(true);});
        const initialId=Number(new URL(location.href).searchParams.get('appointment_id'));
        load(Number.isSafeInteger(initialId)&&initialId>0?initialId:null).then(()=>{if(initialId>0&&!selected)select(initialId);});
        poll.start();
    }
    return {latest,polling,bookingLabel,bookingIndicator,time,money,readResponse,start};
}));
