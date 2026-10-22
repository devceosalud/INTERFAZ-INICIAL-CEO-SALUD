(function(root){
    'use strict';
    const labels={start:'Iniciar atención',finish:'Finalizar atención',indicate:'Reevaluación',withdraw:'Retirar indicación de reevaluación'};
    const careLabels={PROGRAMADO:'Programada',CONFIRMADO:'Confirmada',EN_ESPERA:'Paciente presente',LLAMANDO:'Llamando',EN_ATENCION:'Atención iniciada',ATENDIDO:'Atendido',RETIRO:'Retiro',CANCELADO:'Cancelada',NO_ASISTIO:'No asistió'};
    const doc=root.document,board=doc?.querySelector('#medical-board');if(!board)return;
    const $=id=>doc.getElementById('medical-'+id),node=(name,text,cls)=>{const e=doc.createElement(name);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
    let page=1,last=1,selected=null,detail=null,busy=false,loading=0,detailEpoch=0;
    // Retain the exact payload/key after an uncertain response. A fresh operation never reuses it.
    const retries=new Map();
    async function request(url,data){
        const response=await root.fetch(url,{method:data?'POST':'GET',headers:{Accept:'application/json','Content-Type':'application/json',
            'X-CSRF-TOKEN':doc.querySelector('meta[name=csrf-token]').content},...(data?{body:JSON.stringify(data)}:{})});
        return root.AdmissionBoard.readResponse(response);
    }
    function badges(parent,a){
        for(const b of a.presentation.badges){parent.appendChild(node('span',b.etiqueta,'admission-tag'));}
    }
    async function load(){
        const epoch=++loading,data=Object.fromEntries(new root.FormData($('filters')));data.page=page;
        $('notice').textContent='Cargando mis citas…';
        try{
            const result=await request(board.dataset.feed,data);if(epoch!==loading)return;
            last=result.last_page;$('rows').replaceChildren();
            for(const a of result.rows){
                const row=node('tr');row.dataset.appointmentId=a.id;row.tabIndex=0;row.style.setProperty('--medical-row-bg',a.presentation.fondo);
                if(a.id===selected)row.classList.add('is-selected');
                for(const text of [a.hora,a.patient,a.service+' · '+(a.site||'Sin sede'),careLabels[a.estado_cita]||a.estado_cita])row.appendChild(node('td',text));
                badges(row.children[1],a);
                row.addEventListener('click',()=>select(a.id));row.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();select(a.id);}});
                $('rows').appendChild(row);
            }
            if(!result.rows.length){const row=node('tr'),cell=node('td','Sin citas autorizadas para los filtros seleccionados.');cell.colSpan=4;row.appendChild(cell);$('rows').appendChild(row);}
            $('page').textContent='Página '+result.page+' de '+last+' · '+result.total+' citas';
            $('prev').disabled=page<=1;$('next').disabled=page>=last;$('notice').textContent='';
        }catch(e){if(epoch===loading)$('notice').textContent=e.message;}
    }
    async function select(id){
        const epoch=++detailEpoch;selected=id;detail=null;$('detail').replaceChildren(node('p','Cargando atención…'));
        try{const result=await request(board.dataset.base+'/'+id);if(epoch!==detailEpoch)return;detail=result;render();board.dataset.mobileDetail='true';}
        catch(e){if(epoch===detailEpoch)$('notice').textContent=e.message;}
    }
    async function save(action,data,path){
        if(busy||!detail)return;
        const id=detail.appointment.id,signature=JSON.stringify([id,action,data]);
        const payload=retries.get(signature)||{...data,request_key:root.crypto.randomUUID()};retries.set(signature,payload);
        busy=true;$('detail').querySelectorAll('button,input,textarea').forEach(e=>e.disabled=true);
        try{
            const result=await request(board.dataset.base+'/'+id+path,payload);
            retries.delete(signature);if(selected===id){detail=result;render();}
            await load();$('notice').textContent='Cambio guardado y auditado.';
        }catch(e){$('notice').textContent=e.message;}
        finally{busy=false;$('detail').querySelectorAll('button,input,textarea').forEach(e=>e.disabled=false);}
    }
    function render(){
        const a=detail.appointment,container=$('detail');container.replaceChildren();
        const head=node('section');head.append(node('h3',a.patient),node('p',a.fecha+' '+a.hora+' · '+a.service),node('p',careLabels[a.estado_cita]||a.estado_cita),
            node('p',a.motivo_consulta||'Motivo no registrado'));badges(head,a);container.appendChild(head);
        const actions=node('div',undefined,'admission-care-actions');
        for(const action of detail.actions){
            const button=node('button',labels[action],'admission-btn admission-btn--primary');button.type='button';button.dataset.medicalAction=action;
            button.addEventListener('click',()=>{
                if(busy)return;
                let reason='';
                if(['indicate','withdraw'].includes(action)){
                    reason=root.prompt(action==='indicate'?'Motivo de reevaluación pendiente. Todavía no significa listo para regresar.':'Motivo para retirar la indicación médica.');
                    if(reason===null)return;reason=reason.trim();if(reason.length<3||reason.length>500){$('notice').textContent='Indica un motivo de 3 a 500 caracteres.';return;}
                }
                if(!root.confirm(labels[action]+' · '+a.patient+'\n'+(action==='finish'?'Se finaliza la atención, sin registrar salida física.':action==='indicate'?'Se marcará PENDIENTE DE REEVALUACIÓN, conservando el color actual.':'Se registrará el responsable y la hora real.')+'\n¿Confirmas?'))return;
                save(action,{expected_state:a.estado_cita,revision:detail.revision,confirmed:true,reason},'/care/'+action);
            });actions.appendChild(button);
        }
        container.appendChild(actions);
        if(a.reevaluation.ready)container.appendChild(node('p','LISTO PARA REEVALUACIÓN · Continúa la misma atención y finalízala cuando corresponda.','admission-empty'));
        else if(a.reevaluation.pending)container.appendChild(node('p','PENDIENTE DE REEVALUACIÓN · No se puede finalizar hasta que esté listo para reevaluación o retires justificadamente la indicación. Admisión verificará los procedimientos o marcará listo conforme a sus reglas.','admission-empty'));
        const p=detail.procedures,section=node('section');section.appendChild(node('h4','Procedimientos: '+p.completed_count+' realizados · '+p.pending_count+' pendientes'));
        for(const item of p.items)section.appendChild(node('p',item.label+' · '+(item.completed?'Realizado por '+item.completed_by:'Pendiente')+' · solicitado por '+item.requested_by));
        if(!p.items.length)section.appendChild(node('p','No hay procedimientos solicitados. Una lista vacía no activa listo automáticamente.'));
        if(p.can_select){
            const form=node('form',undefined,'medical-form'),selectedKeys=new Set(p.items.map(i=>i.key));
            for(const [key,label]of Object.entries(p.catalog)){
                const line=node('label',undefined,'medical-procedure'),input=node('input');input.type='checkbox';input.name='procedures';input.value=key;input.checked=selectedKeys.has(key);
                line.append(input,node('span',label));form.appendChild(line);
            }
            const other=node('textarea');other.maxLength=500;other.placeholder='Otro procedimiento (opcional)';other.setAttribute('aria-label','Otro procedimiento');
            const button=node('button','Revisar procedimientos','admission-btn');button.type='submit';form.append(other,button);
            let customId=root.crypto.randomUUID();
            form.addEventListener('submit',e=>{
                e.preventDefault();if(busy)return;
                const procedures=Array.from(form.querySelectorAll('input:checked')).map(i=>i.value);
                const custom=p.items.filter(i=>i.custom).map(i=>({id:i.key.slice(2),label:i.label}));
                if(other.value.trim())custom.push({id:customId,label:other.value.trim()});
                if(!root.confirm('Confirmar los procedimientos solicitados para '+a.patient+'. Se conservará el historial y Admisión verificará su realización.'))return;
                save('procedures',{procedures,custom_procedures:custom,expected_version:p.version},'/procedures');
            });section.appendChild(form);
        }
        container.appendChild(section);
        const audit=node('section');audit.appendChild(node('h4','Historial médico'));
        for(const event of detail.events)audit.appendChild(node('p',event.type.replaceAll('_',' ')+' · '+event.actor+' · '+
            new Date(event.at).toLocaleString('es-PE',{timeZone:'America/Lima'})+(event.reason?' · '+event.reason:'')));
        container.appendChild(audit);
        // A successful save can render new controls while its list refresh is still in flight.
        // Keep those controls disabled until the whole operation has completed.
        container.querySelectorAll('button,input,textarea').forEach(e=>e.disabled=busy);
    }
    $('filters').addEventListener('submit',e=>{e.preventDefault();if(busy)return;page=1;detailEpoch++;selected=null;detail=null;$('detail').replaceChildren();delete board.dataset.mobileDetail;load();});
    $('prev').addEventListener('click',()=>{if(!busy&&page>1){page--;load();}});
    $('next').addEventListener('click',()=>{if(!busy&&page<last){page++;load();}});
    $('back').addEventListener('click',()=>{delete board.dataset.mobileDetail;});
    load();
})(typeof window==='undefined'?globalThis:window);
