(function(root,factory){
    const api=factory();
    if(typeof module==='object'&&module.exports) module.exports=api;
    else {root.AdmissionBoard=api;document.addEventListener('DOMContentLoaded',()=>api.start(document));}
}(typeof window!=='undefined'?window:globalThis,function(){
    'use strict';
    const careLabels={PROGRAMADO:'Programado',CONFIRMADO:'Confirmado (atención)',PACIENTE_LLEGO:'Paciente llegó',EN_ESPERA:'En espera',LLAMANDO:'Llamando',EN_ATENCION:'En consultorio',ATENDIDO:'Atendido',CANCELADO:'Cancelado',NO_ASISTIO:'No asistió',RETIRO:'Retiro',REEVALUACION:'Reevaluación (estado heredado)'};
    const actionLabels={EN_ESPERA:'Registrar llegada',LLAMANDO:'Registrar llamado',EN_ATENCION:'Iniciar atención',ATENDIDO:'Finalizar atención',SALIDA_REGISTRADA:'Registrar salida',INDICAR_REEVALUACION:'Reevaluación',RETIRAR_REEVALUACION:'Retirar reevaluación',INICIAR_REEVALUACION:'Iniciar reevaluación'};
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
    // Admisión-only presentation override. The shared operational palette stays intact.
    function admissionBackground(presentation){const color=presentation?.fringe?.color||presentation?.fondo||'#ffffff';return color.toLowerCase()==='#99f6e4'?'#74dccb':color;}
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
    function rowActivation(event){return !event.target.closest('button,a,input,select,textarea,label,summary')&&(event.type==='click'||event.type==='keydown'&&['Enter',' '].includes(event.key));}
    function start(doc){
        const board=doc.getElementById('admission-board');if(!board)return;
        const $=id=>doc.getElementById('admission-'+id), form=$('filters');
        const listRequest=latest(),detailRequest=latest(),availabilityRequest=latest();
        let detailDirty=false, procedureRequest=null, observationDraft='', selectionDraft=null, customProcedureDraft=null, selectionDraftVersion=null, observationRequest=null, directRequest=null, activeDetailTab='care', documentDraft=false, patientDocuments=null, currentFeed=null;
        const updateDirty=()=>{detailDirty=Boolean(observationDraft||selectionDraft||documentDraft);};
        const clearDraft=()=>{observationDraft='';selectionDraft=null;customProcedureDraft=null;selectionDraftVersion=null;observationRequest=null;detailDirty=false;documentDraft=false;patientDocuments=null;};
        let page=1,total=0,selected=null,busy=false,confirming=false,detail=null,filtersSnapshot='',revision='',loading=false,pendingLoad=null,historyBefore=null,historyFilter='';
        let focusTarget=null,highlightId=null,highlightUntil=0;
        const node=(tag,text,className)=>{const n=doc.createElement(tag);if(text!==undefined&&text!==null)n.textContent=String(text);if(className)n.className=className;return n;};
        const append=(parent,...children)=>{children.forEach(child=>parent.appendChild(child));return parent;};
        const link=(label,url,primary=false)=>{const a=node('a',label,'admission-btn'+(primary?' admission-btn--primary':''));a.href=url;return a;};
        const tag=(text,kind='')=>node('span',text,'admission-tag'+(kind?' admission-tag--'+kind:''));
        const notice=text=>{$('notice').textContent=text||'';};
        const data=()=>Object.fromEntries(new FormData(form).entries());
        const agendaUrl=(doctorId,fecha)=>{const u=new URL(board.dataset.agenda,location.origin);u.searchParams.set('fecha',fecha);if(doctorId)u.searchParams.set('doctor_id',doctorId);return u.href;};
        const replayKeys=new Map();
        async function request(url,options={}){
            let signature;
            if(options.method&&['POST','PATCH'].includes(options.method)&&options.body){const body=JSON.parse(options.body);if(body.request_key){delete body.request_key;signature=JSON.stringify([url,body]);if(!replayKeys.has(signature))replayKeys.set(signature,crypto.randomUUID());options={...options,body:JSON.stringify({...body,request_key:replayKeys.get(signature)})};}}
            const value=await readResponse(await fetch(url,{credentials:'same-origin',...options,headers:{Accept:'application/json','X-CSRF-TOKEN':doc.querySelector('meta[name="csrf-token"]')?.content||'',...(options.body?{'Content-Type':'application/json'}:{}),...options.headers}}));if(signature)replayKeys.delete(signature);return value;}
        function clearDetail(){board.dataset.detailOpen='false';selected=null;detail=null;clearDraft();detailRequest.cancel();$('detail').replaceChildren(node('p','Selecciona una cita para consultar su información y acciones autorizadas.','admission-empty'));}
        function description(label,value){const box=node('div');append(box,node('dt',label),node('dd',value??'—'));return box;}
        function section(title){const box=node('section');box.appendChild(node('h4',title));return box;}
        function progressive(section){const box=node('details',null,'admission-history-disclosure'),heading=section.querySelector('h4');box.appendChild(node('summary',heading?.textContent||'Historial'));heading?.remove();box.appendChild(section);return box;}
        function economic(dl,e){if(e.financial_status)dl.appendChild(description('Estado económico',e.financial_status.label));append(dl,description('Precio neto',money(e.precio)),description(e.fuente==='LEGACY_SNAPSHOT'?'Abonado histórico':'Pagos registrados',e.fuente==='UNALLOCATED'?'Por conciliar':money(e.pagado)),description('Crédito aplicado',money(e.credito)),description('Saldo',e.fuente==='UNALLOCATED'?'Por conciliar':money(e.saldo)));}
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
            if(busy||loading||confirming||!detail)return;
            const id=detail.appointment.id;
            const body={expected_version:action==='selection'?(selectionDraftVersion??detail.procedures.version):detail.procedures.version,...values};
            const signature=JSON.stringify([id,action,body]);
            if(procedureRequest?.signature!==signature)procedureRequest={signature,key:crypto.randomUUID()};
            body.request_key=procedureRequest.key;
            busy=true;
            try {
                await request(board.dataset.base+'/'+id+'/procedures/'+action,{method:'POST',body:JSON.stringify(body)});
                procedureRequest=null;if(action==='selection'){selectionDraft=null;customProcedureDraft=null;selectionDraftVersion=null;}updateDirty();busy=false;
                await select(id);
                await load(id);
                notice('Procedimientos y reevaluación actualizados.');return true;
            }catch(error){busy=false;notice(error.message);return false;}
        }
        async function reviewDecision(action) {
            if(busy||loading||confirming||!detail||!window.Swal)return;
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
            if(busy||loading||confirming||!detail?.reevaluation?.direct?.can_update||!window.Swal)return;
            const id=detail.appointment.id,current=detail.reevaluation.direct;
            confirming=true;let answer;
            const pendingExams=Math.max(0,(detail.procedures?.review?.requested_count||0)-(detail.procedures?.review?.completed_count||0));
            try{answer=await window.Swal.fire({title:current.marked?'Retirar marca manual':'Autorizar reevaluación manual',
                text:current.marked?'Se desactiva la autorización manual, conservando el historial.':'Se autoriza el retorno sin marcar exámenes como realizados ni crear cita/cobro.',
                ...(!current.marked?{input:'textarea',inputLabel:pendingExams?'Motivo obligatorio: hay exámenes pendientes':'Motivo (opcional)',
                    inputAttributes:{maxlength:'500'},inputValidator:value=>pendingExams&&String(value||'').trim().length<3?'Explica el retorno con exámenes pendientes.':undefined}:{}),
                showCancelButton:true,confirmButtonText:'Confirmar marca',cancelButtonText:'Volver',focusCancel:true});}finally{confirming=false;}
            if(!answer?.isConfirmed||selected!==id)return;
            const body={marked:!current.marked,expected_marked:current.marked,expected_version:current.version,
                reason:String(answer.value||'').trim()};
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
            const labels={READY:'Exámenes verificados',NOT_READY:requested?'Pendiente de procedimientos':'Sin procedimientos solicitados',WITHDRAWN:'Reevaluación retirada',SUSPENDED:'Autorización manual suspendida',INACTIVE:'Reevaluación inactiva'};
            const step=unified.resumed?'Reevaluación en consultorio':unified.ready?'Listo para reevaluación':unified.pending?'Pendiente de reevaluación':unified.pre_pending?'Pre-reevaluación sin confirmar':labels[data.review.status]||data.review.status;
            const status=node('div',null,'admission-procedure-status');append(status,node('strong',step),tag(requested?completed+' de '+requested+' realizados':'Sin procedimientos',unified.ready?'ready':unified.pre_pending||unified.pending?'pending':'neutral'));area.appendChild(status);
            if(direct&&(direct.version||direct.can_update)){
                const box=node('div',null,'admission-direct-return');
                box.appendChild(node('strong',direct.active?'Retorno manual autorizado':direct.marked?'Marca manual conservada · inactiva':'Autorizar reevaluación manual'));
                if(direct.actor)box.appendChild(node('small',(direct.marked?'Autorizó: ':'Último cambio: ')+direct.actor.name+' · '+new Date(direct.at).toLocaleString('es-PE',{timeZone:'America/Lima'})));
                if(direct.can_update){const button=node('button',direct.marked?'Retirar marca manual directa':'Autorizar reevaluación manual','admission-btn');button.type='button';button.dataset.directReevaluation='true';button.addEventListener('click',toggleDirect);box.appendChild(button);}area.appendChild(box);
            }
            if(requested){const meter=node('progress',null,'admission-procedure-progress');meter.max=requested;meter.value=completed;meter.setAttribute('aria-label','Progreso de procedimientos');area.appendChild(meter);}
            let explanation=!requested?'No hay procedimientos confirmados para esta cita.':completed+' de '+requested+' procedimientos realizados.';
            if(unified.pre_pending)explanation+=' Admisión o Comercial debe confirmar primero la indicación médica.';
            else if(unified.pending)explanation+=' La reevaluación está confirmada; se espera el retorno.';
            else if(unified.resumed)explanation+=' El médico ya retomó la atención del paciente.';
            if(pending.length)explanation+=' Falta '+pending.map(p=>p.label).join(', ')+'.';
            if(data.review.status==='READY')explanation+=data.review.origin==='MANUAL'?' Envío manual autorizado; los pendientes se conservan.':' Paciente listo para volver al médico.';
            else if(unified.pending&&direct?.can_update)explanation+=' Puedes autorizar el retorno manual con motivo, conservando los exámenes pendientes.';
            else if(!unified.indicated&&data.can_send&&completed)explanation+=' Puedes enviarlo manualmente si corresponde.';
            else if(!unified.indicated&&requested&&!completed)explanation+=' Registra al menos uno realizado antes de considerar un envío manual.';
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
                append(label,node('span',p.label));append(line,label,node('small',(p.completed?'Realizado':'Pendiente')+' · Solicitado por '+p.requested_by+(p.completed_by?' · Realizado por '+p.completed_by:'')));list.appendChild(line);
            }
            area.insertBefore(list,area.querySelector('.admission-procedure-explanation'));
            const directPanel=area.querySelector('.admission-direct-return');if(data.can_complete&&directPanel){const meter=area.querySelector('progress');if(meter)area.insertBefore(meter,directPanel);area.insertBefore(list,directPanel);}
            const decisions=node('div',null,'admission-detail-actions');
            if(!unified.indicated&&data.can_send&&data.review.status!=='READY'){const send=node('button','Enviar a reevaluación','admission-btn');send.type='button';send.addEventListener('click',()=>reviewDecision('send'));decisions.appendChild(send);}
            if(data.can_withdraw){const withdraw=node('button','Retirar envío por procedimientos','admission-btn');withdraw.type='button';withdraw.addEventListener('click',()=>reviewDecision('withdraw'));decisions.appendChild(withdraw);}
            area.appendChild(decisions);
            if(data.can_select){
                const button=node('button','Seleccionar procedimientos','admission-btn admission-btn--primary');button.type='button';
                button.addEventListener('click',()=>window.ProcedurePicker.open({doc,data,trigger:button,save:async(values,version)=>{selectionDraftVersion=version;return await procedureChange('selection',values);}}));area.appendChild(button);
            }
            if(unified.history?.length){
                const audit=node('details',null,'admission-reevaluation-audit'),title=node('summary','Historial de reevaluación'),entries=node('div');
                append(audit,title,entries);let auditPage=unified.history_meta?.page||1,loaded=0;
                const names={REEVALUACION_MEDICA_INDICADA:'Indicación médica · pre-reevaluación',REEVALUACION_MEDICA_RETIRADA:'Indicación médica retirada',REEVALUACION_OPERATIVA_CONFIRMADA:'Confirmación operativa',REEVALUACION_MEDICA_RETOMADA:'Segunda evaluación médica iniciada',REEVALUACION_RECHAZADA_POR_PACIENTE:'Cierre administrativo · paciente rechaza el retorno (sin segunda evaluación)',REEVALUACION_MANUAL_MARCADA:'Marca directa autorizada',REEVALUACION_MANUAL_DESMARCADA:'Marca directa retirada',SEND:'Envío por procedimientos autorizado',WITHDRAW:'Envío por procedimientos retirado',SELECTION:'Solicitud confirmada',COMPLETION:'Realización corregida'};
                const states={READY:'Listo',NOT_READY:'Pendiente',SUSPENDED:'Autorización suspendida',WITHDRAWN:'Envío retirado'};
                const addEvents=events=>{for(const event of events){const line=node('p',(names[event.action]||event.action)+' · '+event.actor.name+' · '+new Date(event.at).toLocaleString('es-PE',{timeZone:'America/Lima'}));line.dataset.auditEvent=event.id;
                    if(event.source==='PROCEDURES')line.appendChild(node('small',event.completed+' de '+event.requested+' · '+(states[event.status]||event.status)+(event.reason&&['SEND','WITHDRAW'].includes(event.action)?' · '+event.reason:'')));
                    else if(event.reason)line.appendChild(node('small',event.reason));entries.appendChild(line);loaded++;}title.textContent='Historial de reevaluación · '+loaded+' movimientos';};
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
            if(busy||loading||confirming||!detail||!window.Swal)return;
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
        async function physicianReevaluation(action) {
            if(busy||loading||confirming||!detail||!window.Swal)return;
            const id=detail.appointment.id,withdrawing=action==='withdraw',resuming=action==='resume';
            confirming=true;let answer;
            try {
                answer=await window.Swal.fire({
                    title:resuming?'Iniciar reevaluación':withdrawing?'Retirar indicación de reevaluación':'Indicar reevaluación',
                    text:resuming?'Confirma que el paciente regresó al consultorio. Se registrará el inicio de la segunda atención.':
                        withdrawing?'La retirada de la indicación médica quedará auditada.':
                            'Se registrará la indicación médica. Admisión o Comercial deberá confirmar la reevaluación antes del retorno.',
                    ...(withdrawing?{input:'textarea',inputLabel:'Motivo obligatorio',inputAttributes:{maxlength:'500'},
                        inputValidator:value=>String(value||'').trim().length<3?'Indica el motivo.':undefined}:{}),
                    showCancelButton:true,confirmButtonText:'Confirmar',cancelButtonText:'Volver',focusCancel:true
                });
            } finally {confirming=false;}
            if(!answer?.isConfirmed||selected!==id)return;
            busy=true;
            try {
                await request(board.dataset.base+'/'+id+'/physician-reevaluation/'+action,{
                    method:'POST',body:JSON.stringify({expected_state:'EN_ATENCION',confirmed:true,
                        request_key:crypto.randomUUID(),...(withdrawing?{reason:String(answer.value).trim()}:{})})
                });
                busy=false;await select(id);await load(id);
                notice(resuming?'Reevaluación iniciada: ahora puedes finalizar al terminar la atención.':
                    withdrawing?'Indicación retirada con auditoría.':'Indicación médica registrada: pre-reevaluación sin confirmar.');
            }catch(error) {busy=false;notice(error.message);}
        }
        async function confirmOperationalReevaluation() {
            if(busy||loading||confirming||!detail?.can_confirm_operational_reevaluation||!window.Swal)return;
            const id=detail.appointment.id;confirming=true;let answer;
            try{answer=await window.Swal.fire({
                title:'Confirmar reevaluación pendiente',
                text:'Admisión o Comercial confirma el seguimiento indicado por el médico. Esto no acredita exámenes realizados.',
                showCancelButton:true,confirmButtonText:'Confirmar reevaluación',cancelButtonText:'Volver',focusCancel:true
            });}finally{confirming=false;}
            if(!answer?.isConfirmed||selected!==id)return;
            busy=true;
            try{
                await request(board.dataset.base+'/'+id+'/confirm-reevaluation',{method:'POST',
                    body:JSON.stringify({expected_state:'EN_ATENCION',confirmed:true,request_key:crypto.randomUUID()})});
                busy=false;await select(id);await load(id);
                notice('Reevaluación confirmada; revisa los exámenes o autoriza el retorno manual.');
            }catch(error){busy=false;notice(error.message);}
        }
        async function closeDeclinedReevaluation() {
            if(busy||loading||confirming||!detail?.can_decline_operational_reevaluation||!window.Swal)return;
            const id=detail.appointment.id;
            confirming=true;let answer;
            try{answer=await window.Swal.fire({
                title:'Paciente no continuará con la reevaluación',
                text:'Esta acción cierra administrativamente la primera atención porque el paciente decidió no regresar. No se registrará una reevaluación médica ni exámenes realizados.',
                input:'textarea',inputLabel:'Motivo obligatorio (registrar decisión del paciente)',
                inputAttributes:{maxlength:'500'},
                inputValidator:value=>String(value||'').trim().length<3?'Indica el motivo de la decisión del paciente.':undefined,
                showCancelButton:true,confirmButtonText:'Cerrar por rechazo del paciente',
                cancelButtonText:'Volver',focusCancel:true,icon:'warning'
            });}finally{confirming=false;}
            if(!answer?.isConfirmed||selected!==id)return;
            busy=true;
            try{
                await request(board.dataset.base+'/'+id+'/decline-reevaluation',{method:'POST',
                    body:JSON.stringify({expected_state:'EN_ATENCION',confirmed:true,
                        request_key:crypto.randomUUID(),reason:String(answer.value).trim()})});
                busy=false;await select(id);await load(id);
                notice('Atención cerrada por decisión del paciente. Reevaluación no realizada; Admisión puede registrar la salida.');
            }catch(error){busy=false;notice(error.message);}
        }
        function presentationName(parent, name, presentation) {
            const label=node('span',name,'admission-patient-name');
            if(presentation?.strikethrough===true)label.style.textDecoration='line-through';
            parent.appendChild(label);
            window.AgendaAppointmentIndicators.append(doc,parent,presentation?.badges||[],{limit:3});
        }
        function renderRows(payload){
            renderReschedules(payload.reschedules);
            currentFeed=payload;const scrollBox=board.querySelector('.admission-table-scroll'),scrollTop=scrollBox.scrollTop,focused=doc.activeElement?.closest?.('tr[data-appointment-id]')?.dataset.appointmentId;total=payload.timeline_total??payload.total;page=payload.page;const body=$('rows');body.replaceChildren();
            const byAppointment=new Map(payload.rows.map(r=>[r.id,r]));
            const timeline=payload.timeline||payload.rows.map(r=>({type:'appointment',id:r.id}));
            for(const entry of timeline){
                if(entry.type==='reschedule'){const tr=node('tr');tr.className='admission-reschedule-row';tr.dataset.historyEventId=entry.event_id;tr.style.setProperty('--ad-status-background','#fecaca');tr.style.setProperty('--ad-status-color','#bd5963');
                    const hour=node('td');append(hour,node('strong',entry.from_time),node('small',entry.from_date.slice(5).split('-').reverse().join('/')));const info=node('td');info.colSpan=3;info.className='admission-reschedule-info';append(info,node('strong','REPROGRAMADA · '+(entry.patient||'Cita visible')),node('p','Horario anterior: '+entry.from_date+' '+entry.from_time+' → '+entry.to_date+' '+entry.to_time),node('small','Cita vigente: '+entry.current_date+' '+entry.current_time+' · Referencia histórica, no ocupa horario.'),link('Ver cita reprogramada',entry.admission_url));append(tr,hour,info);body.appendChild(tr);continue;}
                const row=byAppointment.get(entry.id);if(!row)continue;const tr=node('tr');tr.dataset.appointmentId=row.id;if(row.id===selected)tr.className='is-selected';tr.tabIndex=0;tr.setAttribute('aria-selected',String(row.id===selected));tr.setAttribute('aria-label','Cita de '+row.patient+' a las '+row.hora);
                if(row.id===highlightId&&Date.now()<highlightUntil)tr.classList.add('admission-focus-target');
                const activate=event=>{if(!rowActivation(event))return;if(event.type==='keydown')event.preventDefault();if(selected===row.id){board.dataset.detailOpen='true';board.dataset.mobileDetail='true';return;}select(row.id);};tr.addEventListener('click',activate);tr.addEventListener('keydown',activate);
                const tones={PROGRAMADO:'#c5a13e',LISTO_REEVALUACION:'#ce7a30',PRESENTE:'#32948c',PAGO_REAL:'#bd9c39',DESACTIVADA:'#bd5963',REEMBOLSO_SOLICITADO:'#bd5963',SALIDA_REGISTRADA:'#6f9c97',ATENDIDO:'#74dccb'};
                tr.title=row.presentation?.fringe?.description||'';
                tr.style.setProperty('--ad-status-color',tones[row.presentation?.fringe?.key]||row.presentation?.fringe?.color||'#dce8e9');
                // The server owns the full-row palette; do not mistake the
                // accent/border colour for the background of an appointment.
                tr.style.setProperty('--ad-status-background',admissionBackground(row.presentation));
                const cell=(text,small)=>{const td=node('td',text);if(small)td.appendChild(node('small',small));tr.appendChild(td);return td;};
                const date=cell();append(date,node('strong',row.hora),node('small',row.fecha.slice(5).split('-').reverse().join('/')));
                if(row.reevaluation?.ready) tr.classList.add('is-ready-for-reevaluation');
                const patient=cell();presentationName(patient,row.patient,{...row.presentation,badges:[]});
                const meta=node('div',null,'admission-patient-meta'),reason=node('small',row.motivo_resumen||'Motivo: no registrado','admission-reason-summary');reason.title=row.motivo_resumen||'Motivo: no registrado';append(meta,node('small','H.C. '+(row.historia||'—')+' · '+(row.documento||'—')),reason);patient.appendChild(meta);
                if(row.reschedule_origin){const h=row.reschedule_origin;patient.appendChild(node('small','REPROGRAMADA · desde '+h.from_date.split('-').reverse().join('/')+' '+h.from_time,'admission-reschedule-origin'));}
                cell(row.doctor,row.servicio);const status=node('div',null,'admission-row-state');patient.appendChild(status);append(status,tag(careLabels[row.estado_cita]||row.estado_cita,row.estado_cita==='ATENDIDO'?'attended':row.grupo==='desactivadas'?'inactive':['PROGRAMADO','CONFIRMADO'].includes(row.estado_cita)?'pending':'neutral'));const booking=bookingIndicator(doc,row.estado_agenda);if(booking)status.appendChild(booking);if(row.economy?.financial_status)status.appendChild(node('small',row.economy.financial_status.label,'admission-economic-state'));
                const procedures=cell();procedures.className='admission-signals-cell';window.AgendaAppointmentIndicators.append(doc,procedures,(row.presentation?.badges||[]).filter(b=>['CONFIRMADA_SIN_ADELANTO','TELECONSULTA','ADICIONAL','FUERA_HORARIO','HERIDA','POSTOPERATORIO','CONCILIAR_PAGO'].includes(b.clave)),{limit:3});const progress=row.procedure_progress||{requested:0,completed:0,status:'NOT_READY'},ready=row.reevaluation?.ready??progress.status==='READY';
                if(progress.requested)procedures.appendChild(node('span',progress.completed+' de '+progress.requested+' realizados'));
                const review=row.reevaluation||{};
                const title=review.declined?'Reevaluación no realizada (rechazada)':review.resumed?'Reevaluación en consultorio':ready?'Listo para reevaluación':
                    review.pending?'Confirmada, pendiente':review.pre_pending?'Por confirmar · Pre-reevaluación sin confirmar':
                    progress.status==='NOT_READY'?(progress.requested?'Exámenes pendientes':'Sin solicitud'):
                    ({WITHDRAWN:'Retirado',SUSPENDED:'Suspendido',INACTIVE:'Inactivo',READY:'Exámenes verificados'}[progress.status]||progress.status);
                procedures.appendChild(tag(title,ready?'ready':review.pending||review.pre_pending?'pending':'neutral'));
                if(ready&&review.awaiting_confirmation)procedures.appendChild(tag('Exámenes completos · falta confirmación operativa','pending'));
                body.appendChild(tr);
            }
            if(!timeline.length){const tr=node('tr'),td=node('td','Sin citas para este filtro. '),clear=node('button','Restablecer filtros','admission-btn');clear.type='button';clear.addEventListener('click',()=>$('reset').click());td.colSpan=4;td.appendChild(clear);tr.appendChild(td);body.appendChild(tr);}
            scrollBox.scrollTop=scrollTop;if(focused)body.querySelector('tr[data-appointment-id="'+focused+'"]')?.focus({preventScroll:true});
            for(const [key,count]of Object.entries(payload.kpis))board.querySelectorAll('[data-kpi="'+key+'"]').forEach(target=>target.textContent=count);
            $('count').textContent='· '+payload.total;
            $('reservations').textContent=payload.kpis.reservas+' reservas pendientes · '+payload.kpis.otros+' para revisar';
            $('page').textContent='Página '+page+' de '+Math.max(1,Math.ceil(total/30));$('prev').disabled=page<=1;$('next').disabled=page*30>=total;
            if(payload.can_view_economy===false)return;
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
            if(id!==selected){clearDraft();activeDetailTab=selected===null&&doc.defaultView?.location.hash==='#history'&&String(id)===new URLSearchParams(doc.defaultView.location.search).get('appointment_id')?'history':'care';}
            selected=id;board.dataset.detailOpen='true';board.dataset.mobileDetail='true';
            if(!background){detail=null;$('detail').replaceChildren(node('p','Consultando cita…','admission-empty'));}
            board.querySelectorAll('tr[data-appointment-id]').forEach(tr=>{const active=Number(tr.dataset.appointmentId)===id;tr.classList.toggle('is-selected',active);tr.setAttribute('aria-selected',String(active));});
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
            const head=section('CITA '+a.numero),patientName=node('h3');presentationName(patientName,a.patient,{...a.presentation,badges:(a.presentation.badges||[]).filter(b=>['CONFIRMADA_SIN_ADELANTO','TELECONSULTA','ADICIONAL','FUERA_HORARIO','HERIDA','POSTOPERATORIO'].includes(b.clave))});
            append(head,patientName,tag(careLabels[a.estado_cita]||a.estado_cita,a.estado_cita==='ATENDIDO'?'attended':a.grupo==='desactivadas'?'inactive':'neutral'),node('small',a.fecha+' · '+a.hora+' · H.C. '+(a.historia||'—')+' · '+(a.documento||'—')),node('small',a.doctor+' · '+a.servicio));
            const reason=node('div',null,'admission-consultation-reason');append(reason,node('strong','MOTIVO DE CONSULTA'),node('p',a.motivo_consulta?.trim()||'No registrado'));head.appendChild(reason);root.appendChild(head);
            const actions=node('div',null,'admission-care-actions');
            if(payload.without_deposit_confirmation){const button=node('button','Confirmar sin adelanto','admission-btn');button.type='button';button.dataset.confirmWithoutDeposit='true';button.addEventListener('click',confirmWithoutDeposit);actions.appendChild(button);}
            if(payload.exoneration_confirmation){const button=node('button','Confirmar exoneración autorizada sin cobro','admission-btn');button.type='button';button.addEventListener('click',confirmExoneration);actions.appendChild(button);}
            for(const state of payload.care_actions){const button=node('button',actionLabels[state],'admission-btn'+(['EN_ATENCION','EN_ESPERA','INICIAR_REEVALUACION'].includes(state)?' admission-btn--primary':''));button.type='button';button.addEventListener('click',()=>state==='INDICAR_REEVALUACION'?physicianReevaluation('indicate'):state==='RETIRAR_REEVALUACION'?physicianReevaluation('withdraw'):state==='INICIAR_REEVALUACION'?physicianReevaluation('resume'):transition(state));actions.appendChild(button);}
            if(payload.can_confirm_operational_reevaluation){const confirm=node('button','Confirmar reevaluación pendiente','admission-btn admission-btn--primary');confirm.type='button';confirm.addEventListener('click',confirmOperationalReevaluation);actions.appendChild(confirm);}
            if(payload.can_decline_operational_reevaluation){const decline=node('button','Paciente no desea reevaluación · Finalizar','admission-btn');decline.type='button';decline.addEventListener('click',closeDeclinedReevaluation);actions.appendChild(decline);}
            if(payload.medical_reevaluation?.declined)actions.appendChild(node('p','ATENCIÓN CERRADA · el paciente decidió no realizar la reevaluación. No se ha registrado una segunda evaluación médica.','admission-tag admission-tag--neutral'));
            else if(payload.medical_reevaluation?.ready&&payload.medical_reevaluation?.awaiting_confirmation)actions.appendChild(node('p','LISTO PARA RETORNO · exámenes completos. Admisión o Comercial aún debe confirmar la indicación antes de iniciar la segunda atención.','admission-tag admission-tag--ready'));
            else if(payload.medical_reevaluation?.pre_pending)actions.appendChild(node('p','PRE-REEVALUACIÓN SIN CONFIRMAR · Admisión o Comercial debe confirmar la indicación.','admission-tag admission-tag--pending'));
            else if(payload.medical_reevaluation?.pending)actions.appendChild(node('p','PENDIENTE DE REEVALUACIÓN · revisar exámenes o autorizar retorno manual.','admission-tag admission-tag--pending'));
            else if(payload.medical_reevaluation?.ready)actions.appendChild(node('p','LISTO PARA REEVALUACIÓN · el médico debe iniciar la segunda atención.','admission-tag admission-tag--ready'));
            else if(payload.medical_reevaluation?.resumed)actions.appendChild(node('p','REEVALUACIÓN EN CONSULTORIO · el médico puede finalizar al terminar.','admission-tag admission-tag--ready'));
            if(payload.care_reversal){const reverse=node('button','Retroceder estado','admission-btn');reverse.type='button';reverse.addEventListener('click',()=>reverseCare(payload.care_reversal));actions.appendChild(reverse);}root.appendChild(actions);
            const tabs=node('div',null,'admission-detail-tabs');tabs.setAttribute('role','tablist');tabs.setAttribute('aria-label','Información de la cita');
            const panels={};
            for(const[key,label]of Object.entries({care:'Atención',notes:'Observaciones',money:'Pagos y saldo',history:'Documentos e historial'})){
                const button=node('button',label);button.type='button';button.id='admission-detail-tab-'+key;button.dataset.detailTab=key;button.setAttribute('role','tab');button.setAttribute('aria-controls','admission-detail-panel-'+key);
                const panel=node('div');panel.id='admission-detail-panel-'+key;panel.setAttribute('role','tabpanel');panel.setAttribute('aria-labelledby',button.id);panels[key]=panel;
                button.addEventListener('click',()=>activateDetailTab(key));button.addEventListener('keydown',event=>{const keys=Object.keys(panels);const next=tabKey(event,keys,key);if(next){activateDetailTab(next);tabs.querySelector('[data-detail-tab='+next+']').focus();}});tabs.appendChild(button);
            }
            root.appendChild(tabs);for(const panel of Object.values(panels))root.appendChild(panel);
            function activateDetailTab(key){activeDetailTab=key;if(key==='history')patientDocuments?.load();for(const[k,panel]of Object.entries(panels)){panel.hidden=k!==key;const button=tabs.querySelector('[data-detail-tab='+k+']');button.setAttribute('aria-selected',String(k===key));button.tabIndex=k===key?0:-1;}}
            const proceduresPanel=renderProcedures(payload);if(proceduresPanel)panels.care.appendChild(proceduresPanel);
            if(a.attendance_mode==='TELECONSULTA')panels.care.appendChild(node('p','Teleconsulta registrada. El flujo remoto de atención está pendiente de definición; no se registra presencia ni salida física.'));
            const journey=section('Recorrido de atención'),journeyList=node('ol',null,'admission-journey');for(const[key,label]of Object.entries({llegada:'Llegada',llamado:'Llamado',inicio:'Inicio',fin:'Atendido',salida:'Salida física'}))append(journeyList,append(node('li'),node('time',time(a.tiempos[key])),node('span',label)));journey.appendChild(journeyList);panels.care.appendChild(journey);
            if(a.agenda_url)panels.care.appendChild(link('Abrir en Agenda operativa',a.agenda_url));
            const tasks=renderOperationalTasks(payload);if(tasks)panels.notes.appendChild(tasks);else panels.notes.appendChild(node('p','No tienes acceso a observaciones operativas.','admission-empty'));
            if(payload.can_view_economy!==false){const pricing=section('Situación económica'),dl=node('dl');economic(dl,a.economy);append(pricing,dl,node('small','Fuente: '+a.economy.fuente),node('small','Regla de precio: '+(a.pricing_rule||'Histórica / sin clasificación')));for(const payment of payload.payments||[])pricing.appendChild(node('p',money(payment.amount)+' · '+payment.method+' · '+(payment.recorded_at?new Date(payment.recorded_at).toLocaleString('es-PE',{timeZone:'America/Lima'}):'Sin fecha registrada')));pricing.appendChild(link('Registrar pago / OCR en Agenda',a.agenda_url,true));panels.money.appendChild(pricing);}else{tabs.querySelector('[data-detail-tab=money]').hidden=true;panels.money.appendChild(node('p','Acceso económico restringido.'));}
            const contact=section('Ficha y contacto');append(contact,node('p',payload.contact.telefono||'Teléfono sin registrar'),node('small',payload.contact.telefono_secundario||''),node('p','Responsable: '+(a.responsable||'—')),node('small','Sede: '+(a.sede||'Sin registrar')+' · Canal: '+(payload.contact.canal||'—')+' · Medio: '+(payload.contact.medio||'—')),link('Ficha administrativa del paciente',payload.patient_url));
            if(payload.patient_documents_url&&window.AppointmentPatientDocuments){if(!patientDocuments)patientDocuments=window.AppointmentPatientDocuments.create({doc,url:payload.patient_documents_url,appointmentId:a.id,readResponse,onDirty:value=>{documentDraft=value;updateDirty();},onBusy:value=>{busy=value;}});panels.history.appendChild(patientDocuments.element);}
            const docs=section('Comprobantes de pago');if(!payload.documents.length)docs.appendChild(node('p','Sin comprobantes adjuntos.'));for(const d of payload.documents)docs.appendChild(link('Comprobante del pago #'+d.payment_id,d.download_url));panels.history.appendChild(progressive(docs));
            const operational=section('Documentos operativos');if(!payload.can_view_operational_documents)operational.appendChild(node('p','Acceso restringido a documentos privados.'));else {if(!payload.operational_documents?.length)operational.appendChild(node('p','Sin documentos operativos vigentes.'));for(const d of payload.operational_documents||[])operational.appendChild(link(({PRESCRIPTION:'Receta',REPORT:'Informe',ADMINISTRATIVE:'Administrativo'}[d.kind]||'Documento')+' · '+d.label,d.url));operational.appendChild(link('Adjuntar o reemplazar desde Agenda',a.agenda_url));}panels.history.appendChild(progressive(operational));
            const events=section('Actividad registrada'),ul=node('ul',null,'admission-events');for(const e of payload.events)append(ul,append(node('li'),node('span',new Date(e.at).toLocaleString('es-PE',{timeZone:'America/Lima'})+' · '+e.type.replaceAll('_',' ')),node('small',e.actor||'Sin responsable registrado')));if(!payload.events.length)ul.appendChild(node('li','Sin eventos registrados.'));events.appendChild(ul);panels.history.appendChild(progressive(events));
            const history=section('Historial administrativo · hasta 20 citas visibles');for(const item of payload.history){const article=node('article',null,'admission-history-item');append(article,node('strong',item.fecha+' · '+item.hora),node('p',item.doctor+' · '+item.servicio),node('small',(careLabels[item.estado_cita]||item.estado_cita)+' · '+bookingLabel(item.estado_agenda)));const previous=node('dl');economic(previous,item.economy);article.appendChild(previous);history.appendChild(article);}if(!payload.history.length)history.appendChild(node('p','Sin otras citas visibles.'));panels.history.appendChild(progressive(history));
            if(payload.can_view_economy!==false)panels.history.appendChild(progressive(contact));activateDetailTab(activeDetailTab==='money'&&payload.can_view_economy===false?'care':activeDetailTab);resizeWorkspace();
        }
        function tabKey(event,keys,current){let index=keys.indexOf(current);if(event.key==='ArrowRight')index=(index+1)%keys.length;else if(event.key==='ArrowLeft')index=(index+keys.length-1)%keys.length;else if(event.key==='Home')index=0;else if(event.key==='End')index=keys.length-1;else return null;event.preventDefault();return keys[index];}
        function resizeWorkspace(){const area=board.querySelector('.admission-workspace');if(area&&window.innerWidth>1024)board.style.setProperty('--ad-workspace-height',Math.max(340,window.innerHeight-area.getBoundingClientRect().top-20)+'px');}
        let exonerationRequest=null,withoutDepositRequest=null;
        async function confirmWithoutDeposit(){
            if(busy||loading||confirming||!detail?.without_deposit_confirmation||!window.Swal)return;
            const id=selected,offer=detail.without_deposit_confirmation;confirming=true;let answer;
            try{answer=await window.Swal.fire({title:'Confirmar cita sin adelanto',
                text:'El horario quedará ocupado y el pago seguirá pendiente. Esta excepción no es una exoneración. Registra el motivo.',
                input:'textarea',inputAttributes:{maxlength:'500'},inputValidator:value=>String(value||'').trim().length<5?'Indica un motivo de al menos 5 caracteres.':undefined,
                showCancelButton:true,confirmButtonText:'Confirmar sin adelanto',cancelButtonText:'Cancelar',focusCancel:true});}finally{confirming=false;}
            if(!answer?.isConfirmed||selected!==id||detail?.without_deposit_confirmation?.revision!==offer.revision)return;
            const reason=String(answer.value||'').trim(),signature=JSON.stringify([id,offer.revision,reason]);
            if(withoutDepositRequest?.signature!==signature)withoutDepositRequest={signature,request_key:crypto.randomUUID()};
            busy=true;try{await request(offer.url,{method:'POST',body:JSON.stringify({revision:offer.revision,confirmed:true,reason,request_key:withoutDepositRequest.request_key})});withoutDepositRequest=null;busy=false;await load(id);notice('Cita confirmada; pago pendiente, sin registrar pagos.');}catch(error){notice(error.message);}finally{busy=false;}
        }
        async function confirmExoneration(){
            if(busy||loading||confirming||!detail?.exoneration_confirmation||!window.Swal)return;
            const id=selected,offer=detail.exoneration_confirmation;confirming=true;let answer;
            try{answer=await window.Swal.fire({title:'Confirmar cita exonerada',text:'Se revalidará la disponibilidad y la exoneración autorizada. No se registrará ningún pago.',showCancelButton:true,confirmButtonText:'Confirmar sin cobro',cancelButtonText:'Cancelar',focusCancel:true});}finally{confirming=false;}
            if(!answer?.isConfirmed||selected!==id||detail?.exoneration_confirmation?.revision!==offer.revision)return;
            const signature=JSON.stringify([id,offer.revision]);if(exonerationRequest?.signature!==signature)exonerationRequest={signature,request_key:crypto.randomUUID()};
            busy=true;try{await request(offer.url,{method:'POST',body:JSON.stringify({revision:offer.revision,confirmed:true,request_key:exonerationRequest.request_key})});exonerationRequest=null;busy=false;await load(id);notice('Cita confirmada sin registrar pagos.');}catch(error){notice(error.message);}finally{busy=false;}
        }
        async function transition(state){if(busy||loading||confirming||!detail)return;const id=detail.appointment.id,expected=detail.appointment.estado_cita;
            if(!window.Swal){notice('No se cargó la confirmación de acciones. Vuelve a abrir el panel.');return;}
            const fallback=Boolean(detail.care_fallback)&&['EN_ATENCION','ATENDIDO'].includes(state);
            let result;confirming=true;try{result=await window.Swal.fire({...(fallback?{input:'textarea',inputLabel:'Motivo obligatorio de contingencia administrativa (sin firma médica)',inputAttributes:{maxlength:'500'},inputValidator:v=>String(v||'').trim().length<3?'Indica el motivo.':undefined}:{}),title:actionLabels[state],text:state==='SALIDA_REGISTRADA'?'¿Confirmas que el paciente salió físicamente del establecimiento?':'Se registrará la actuación y su responsable. No cambia pagos ni el precio.',icon:'question',showCancelButton:true,confirmButtonText:state==='SALIDA_REGISTRADA'?'Confirmar salida':'Confirmar',cancelButtonText:'Cancelar'});}finally{confirming=false;}if(!result.isConfirmed||busy||selected!==id||detail?.appointment.estado_cita!==expected)return;
            busy=true;const buttons=Array.from($('detail').querySelectorAll('.admission-care-actions button'));buttons.forEach(b=>b.disabled=true);$('detail').setAttribute('aria-busy','true');notice('Registrando '+(state==='SALIDA_REGISTRADA'?'salida física':'atención')+'…');
            try{
                const dedicatedArrival=state==='EN_ESPERA' && detail?.can_register_arrival;
                const endpoint=board.dataset.base+'/'+id+(dedicatedArrival?'/arrival':'/care');
                const payload=dedicatedArrival?{expected_state:expected,confirmed:true,request_key:crypto.randomUUID()}
                    :{state,expected_state:expected,confirmed:true,...(fallback?{motivo:String(result.value||'').trim()}:{}),request_key:crypto.randomUUID()};
                const result=await request(endpoint,{method:'POST',body:JSON.stringify(payload)});
                if(result.detail){const wasReady=detail.appointment.reevaluation?.ready;detail=result.detail;renderDetail(detail);
                    if(currentFeed){const row=currentFeed.rows.find(r=>r.id===id);if(row)Object.assign(row,detail.appointment);if(wasReady&&!detail.appointment.reevaluation?.ready)currentFeed.kpis.reevaluaciones=Math.max(0,currentFeed.kpis.reevaluaciones-1);renderRows(currentFeed);revision='';}
                    notice('Salida registrada. Se conserva el estado ATENDIDO y sus pagos.');
                }else{busy=false;await load(id);notice('Atención actualizada.');}
            }catch(error){notice(error.message);}finally{busy=false;buttons.forEach(b=>b.disabled=false);$('detail').removeAttribute('aria-busy');}
        }
        async function availability(filters){
            if(board.dataset.clinicalOnly==='1'||!$('availability-panel').open)return;
            const options=Array.from($('doctor').options).filter(o=>o.value&&o.dataset.active==='1'&&(!filters.doctor_id||o.value===filters.doctor_id)&&(!filters.specialty_id||o.dataset.specialty===filters.specialty_id)).slice(0,6);
            $('availability-note').textContent='Primeros '+options.length+' médicos activos del filtro. Disponibilidad informativa; se revalida al reservar.';
            $('availability').replaceChildren();
            try{await availabilityRequest.run(async signal=>Promise.all(options.map(async option=>{const u=new URL(board.dataset.availability,location.origin);u.searchParams.set('doctor_id',option.value);u.searchParams.set('fecha',filters.fecha);if(filters.site_id)u.searchParams.set('site_id',filters.site_id);try{return {option,result:await request(u.href,{signal})};}catch(error){if(error.name==='AbortError')throw error;return {option,error:true};}})),results=>{for(const entry of results){const card=node('article');card.appendChild(node('h3',entry.option.textContent));if(entry.error)card.appendChild(node('p','No se pudo consultar el horario.'));else{const slots=entry.result.slots||[],free=slots.filter(s=>s.estado==='DISPONIBLE');append(card,node('p',slots.length?free.length+' intervalos libres / '+slots.length+' intervalos del horario':'Sin horario configurado'),node('p',free.length?'Primer intervalo libre: '+free[0].inicio:'Sin intervalos libres'),link('Abrir Agenda',agendaUrl(entry.option.value,filters.fecha)));}$('availability').appendChild(card);}});}catch(error){$('availability-note').textContent='No se pudo consultar disponibilidad. Usa Agenda para verificar horarios.';}
        }

        const editing=()=>Boolean(doc.querySelector('.admission-procedure-dialog[open]'))||confirming||detailDirty||Boolean($('detail').querySelector?.('.admission-reevaluation-audit[open]'))||Boolean($('detail').contains(doc.activeElement)&&doc.activeElement?.matches('input,textarea,select,[contenteditable="true"]'));
        function renderReschedules(payload){
            const panel=$('reschedule-panel');if(!panel)return;const items=payload?.items||[];panel.hidden=!items.length&&!payload?.has_more&&!historyBefore;
            $('reschedule-count').textContent='('+items.length+(payload?.has_more?'+':'')+')';const list=$('reschedule-list');list.replaceChildren();
            for(const h of items){const row=node('article',null,'admission-reschedule-reference');row.dataset.historyEventId=h.event_id;
                append(row,node('strong','REPROGRAMADA · '+h.from_date.split('-').reverse().join('/')+' '+h.from_time+' → '+h.to_date.split('-').reverse().join('/')+' '+h.to_time),
                    node('small','Cita vigente: '+h.current_date.split('-').reverse().join('/')+' '+h.current_time),link('Ver cita reprogramada',h.admission_url));list.appendChild(row);}
            $('reschedule-previous').hidden=!payload?.has_more;$('reschedule-latest').hidden=!historyBefore;
            $('reschedule-previous').dataset.cursor=payload?.next_cursor||'';
        }
        $('reschedule-previous')?.addEventListener('click',()=>{if(loading||busy)return;historyBefore=Number($('reschedule-previous').dataset.cursor)||null;load();});
        $('reschedule-latest')?.addEventListener('click',()=>{historyBefore=null;load();});
        async function load(reselect=null,background=false){if(busy||confirming||(background&&editing()))return;
            const snapshot=JSON.stringify(data());if(background&&(doc.hidden||snapshot!==filtersSnapshot))return;
            if(loading){if(!background){pendingLoad={reselect};listRequest.cancel();}return;}
            loading=true;const filters=data();if(snapshot!==historyFilter){historyBefore=null;historyFilter=snapshot;}filters.page=page;if(historyBefore)filters.history_before=historyBefore;if(background&&revision)filters.revision=revision;
            if(focusTarget)filters.focus_appointment_id=focusTarget;
            if(!background){revision='';if(!detailDirty)clearDetail();availabilityRequest.cancel();renderReschedules(null);$('rows').replaceChildren();$('totals').replaceChildren();$('count').textContent='';$('reservations').textContent='';$('availability').replaceChildren();$('scope').textContent='Consultando los filtros seleccionados…';board.querySelectorAll('[data-kpi]').forEach(el=>el.textContent='—');$('prev').disabled=true;$('next').disabled=true;notice('Consultando conjunto filtrado…');$('create').href=agendaUrl(filters.doctor_id,filters.fecha);}
            try{await listRequest.run(signal=>request(board.dataset.feed,{method:'POST',signal,body:JSON.stringify(filters)}),async payload=>{
                if(snapshot!==JSON.stringify(data())||(background&&(busy||editing()||doc.hidden)))return;
                if(payload.unchanged){notice('');if(reselect&&selected===reselect)await select(reselect,true);return;}revision=payload.revision;filtersSnapshot=snapshot;renderRows(payload);notice('');
                $('scope').textContent='Indicadores y totales: '+(payload.scope_total??payload.kpis.total)+' citas del conjunto de búsqueda, del '+filters.fecha+' al '+filters.hasta+'. Listado: '+payload.total+' citas; las referencias históricas no cuentan como citas. Los filtros rápidos solo seleccionan el listado.';
                availability(filters);if(reselect&&!detailDirty&&(!background||selected===reselect))await select(reselect,background);
                if(detailDirty&&!background)notice('Filtros aplicados. Se conserva el borrador del paciente abierto.');updateFilterState();resizeWorkspace();
                if(focusTarget){const id=focusTarget;focusTarget=null;
                    if(payload.focus_found){highlightId=id;highlightUntil=Date.now()+8000;board.dataset.mobileDetail='false';
                        notice('Cita vigente localizada y resaltada.');
                        requestAnimationFrame(()=>requestAnimationFrame(()=>{const row=$('rows').querySelector('tr[data-appointment-id="'+id+'"]');if(!row)return;resizeWorkspace();
                            const box=board.querySelector('.admission-table-scroll'),rect=row.getBoundingClientRect(),view=box.getBoundingClientRect();
                            box.scrollTop+=rect.top-view.top-Math.max(40,(box.clientHeight-rect.height)/2);
                            const visible=row.getBoundingClientRect();
                            if(visible.top<72||visible.bottom>window.innerHeight-16)window.scrollBy({top:visible.top-Math.max(72,(window.innerHeight-visible.height)/2),behavior:'instant'});
                            row.focus({preventScroll:true});row.classList.add('admission-focus-target');}));
                        setTimeout(()=>{highlightId=null;board.querySelectorAll('.admission-focus-target').forEach(row=>row.classList.remove('admission-focus-target'));},8000);
                    }else notice('La cita no aparece en este conjunto autorizado. Revisa la fecha y los filtros o vuelve a consultar la referencia.');
                }
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
        function clearQuick(){ $('group').value='';$('state').value='';$('visual').value='';$('only-reviews').checked=false;$('only-pending-reviews').checked=false;$('reevaluation-stage').value='';}
        function clearCategory(){clearQuick();refreshPickers();page=1;load();}
        $('clear-category').addEventListener('click',clearCategory);
        board.querySelectorAll('[data-group]').forEach(button=>button.addEventListener('click',()=>{const active=button.classList.contains('is-active');clearQuick();$('group').value=active?'':button.dataset.group;refreshPickers();page=1;load();}));
        function refreshPickers(){if(window.jQuery?.fn?.selectpicker)window.jQuery(form).find('select').each(function(){if(window.jQuery(this).data('selectpicker'))window.jQuery(this).selectpicker('refresh');});}
        function updateFilterState(){const filters=data();const advanced=['site_id','specialty_id','estado','grupo','visual'].filter(k=>filters[k]).length+(filters.hasta!==filters.fecha?1:0);$('more-count').textContent=advanced?String(advanced):'';board.querySelectorAll('[data-group]').forEach(button=>{const active=button.dataset.group===(filters.grupo||'')&&!filters.only_reevaluations&&!filters.only_pending_reevaluations;button.classList.toggle('is-active',active);button.setAttribute('aria-pressed',String(active));});const pending=board.querySelector('[data-pending-review-only]');pending.classList.toggle('is-active',Boolean(filters.only_pending_reevaluations));pending.setAttribute('aria-pressed',String(Boolean(filters.only_pending_reevaluations)));
            const names={por_llegar:'Por llegar',en_espera:'En espera',en_consultorio:'En atención',atendidos:'Atendidos'};const active=filters.only_reevaluations?'Listos para reevaluación':filters.reevaluation_stage==='PRE_PENDING'?'Por confirmar':filters.reevaluation_stage==='PENDING'?'Confirmadas, pendientes':filters.only_pending_reevaluations?'Pendientes: por confirmar y confirmadas':names[filters.grupo]||filters.grupo||filters.visual||filters.estado||'';$('active-filter').textContent=active?'Filtro activo: '+active:'Todas las citas del conjunto de búsqueda';$('clear-category').hidden=!active;
            const review=board.querySelector('[data-review-only]');review.classList.toggle('is-active',Boolean(filters.only_reevaluations));review.setAttribute('aria-pressed',String(Boolean(filters.only_reevaluations)));}
        $('reset').addEventListener('click',()=>{form.reset();clearQuick();$('until').value=$('date').value;for(const option of $('doctor').options)option.hidden=false;refreshPickers();page=1;load();});
        board.querySelector('[data-review-only]').addEventListener('click',()=>{const checked=!$('only-reviews').checked;clearQuick();$('only-reviews').checked=checked;refreshPickers();page=1;load();});
        board.querySelector('[data-pending-review-only]').addEventListener('click',()=>{const checked=!$('only-pending-reviews').checked;clearQuick();$('only-pending-reviews').checked=checked;refreshPickers();page=1;load();});
        $('only-reviews').addEventListener('change',()=>{const checked=$('only-reviews').checked;clearQuick();$('only-reviews').checked=checked;refreshPickers();page=1;load();});
        $('only-pending-reviews').addEventListener('change',()=>{const checked=$('only-pending-reviews').checked;clearQuick();$('only-pending-reviews').checked=checked;refreshPickers();page=1;load();});
        $('reevaluation-stage').addEventListener('change',()=>{const stage=$('reevaluation-stage').value;clearQuick();$('reevaluation-stage').value=stage;$('only-pending-reviews').checked=Boolean(stage);refreshPickers();page=1;load();});
        $('availability-panel').addEventListener('toggle',()=>{if($('availability-panel').open)availability(data());resizeWorkspace();});
        $('more-filters').addEventListener('toggle',resizeWorkspace);board.querySelector('.admission-financial-summary').addEventListener('toggle',resizeWorkspace);
        $('back-list').addEventListener('click',()=>{board.dataset.detailOpen='false';board.dataset.mobileDetail='false';$('patients-tab').focus();resizeWorkspace();});
        function listTab(key){for(const name of ['patients','queue']){const active=name===key;$(''+name+'-tab').setAttribute('aria-selected',String(active));$(''+name+'-tab').tabIndex=active?0:-1;$(''+name+'-panel').hidden=!active;}resizeWorkspace();}
        for(const key of ['patients','queue']){$(key+'-tab').addEventListener('click',()=>listTab(key));$(key+'-tab').addEventListener('keydown',event=>{const next=tabKey(event,['patients','queue'],key);if(next){listTab(next);$(next+'-tab').focus();}});}
        doc.addEventListener('operational-queue-select',event=>select(event.detail.id));
        doc.addEventListener('operational-queue-updated',event=>{$('queue-count').textContent=(event.detail?.pre_pending??0)+' por confirmar · '+(event.detail?.pending??0)+' pendientes · '+(event.detail?.active??0)+' listos';});
        window.addEventListener('resize',resizeWorkspace);if(window.ResizeObserver)new ResizeObserver(resizeWorkspace).observe(board.querySelector('.admission-commandbar'));resizeWorkspace();
        const poll=polling({run:()=>load(selected,true),enabled:()=>!doc.hidden&&!busy&&!editing(),alive:()=>board.isConnected});
        doc.addEventListener('visibilitychange',()=>{if(doc.hidden){poll.stop();listRequest.cancel();}else poll.start(true);});
        window.addEventListener('pagehide',()=>{poll.stop();listRequest.cancel();detailRequest.cancel();availabilityRequest.cancel();});
        window.addEventListener('focus',()=>{if(!doc.hidden)poll.start(true);});
        window.addEventListener('pageshow',event=>{if(event.persisted)poll.start(true);});
        const initialParams=new URL(location.href).searchParams;
        const initialId=Number(initialParams.get('appointment_id'));
        if(Number.isSafeInteger(initialId)&&initialId>0){
            focusTarget=initialParams.get('focus')==='1'?initialId:null;clearQuick();$('search').value='';$('specialty').value='';
            const initialDate=initialParams.get('fecha');if(/^\d{4}-\d{2}-\d{2}$/.test(initialDate||'')){$('date').value=initialDate;$('until').value=initialDate;}
            for(const[field,param]of [['doctor','doctor_id'],['site','site_id']]){const value=initialParams.get(param),select=$(field);if(value!==null&&Array.from(select.options).some(option=>option.value===value))select.value=value;}
        }
        load(Number.isSafeInteger(initialId)&&initialId>0?initialId:null).then(()=>{if(initialId>0&&!selected)select(initialId);});
        poll.start();
    }
    return {admissionBackground,rowActivation,latest,polling,bookingLabel,bookingIndicator,time,money,readResponse,start};
}));
