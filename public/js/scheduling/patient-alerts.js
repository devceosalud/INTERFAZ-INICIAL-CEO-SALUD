(function(root){'use strict';
    const at=value=>value?new Date(value).toLocaleString('es-PE',{timeZone:'America/Lima',dateStyle:'short',timeStyle:'short'}):'';
    function node(doc,tag,text){const n=doc.createElement(tag);if(text)n.textContent=text;return n;}
    function badge({doc,summary,request}){
        const b=node(doc,'button','Paciente problema');b.type='button';b.className='patient-alert-badge';b.setAttribute('aria-label','Paciente problema: consultar incidente operativo autorizado');
        let hint=null;
        const remove=()=>{hint?.remove();hint=null;b.removeAttribute('aria-describedby');};
        async function reveal(){remove();const h=node(doc,'div','Consultando alerta autorizada…');h.className='patient-alert-hint';h.id='patient-alert-'+crypto.randomUUID();h.setAttribute('role','tooltip');hint=h;doc.body.append(h);b.setAttribute('aria-describedby',h.id);const r=b.getBoundingClientRect();h.style.top=Math.min(innerHeight-140,r.bottom+7)+'px';h.style.left=Math.max(8,Math.min(r.left,innerWidth-330))+'px';try{const a=await request(summary.url);if(hint===h)h.textContent=a.active?a.reason:'La alerta ya fue resuelta.';}catch(e){if(hint===h)h.textContent='Alerta no disponible para esta sesión.';}}
        b.addEventListener('mouseenter',reveal);b.addEventListener('focus',reveal);b.addEventListener('mouseleave',remove);b.addEventListener('blur',remove);b.addEventListener('keydown',e=>{if(e.key==='Escape')remove();});
        b.addEventListener('click',async e=>{e.stopPropagation();remove();const dialog=node(doc,'dialog');dialog.className='patient-alert-dialog';dialog.setAttribute('aria-label','Incidente operativo del paciente');const close=node(doc,'button','Cerrar');close.type='button';close.addEventListener('click',()=>dialog.close());dialog.append(node(doc,'h2','Alerta operativa del paciente'));const body=node(doc,'div');dialog.append(body,close);doc.body.append(dialog);dialog.addEventListener('close',()=>{dialog.remove();b.focus();},{once:true});dialog.showModal();await panel({doc,url:summary.url,request,root:body,edit:false,onChange:()=>doc.dispatchEvent(new CustomEvent('patient-alert-updated'))});});
        return b;
    }
    async function panel({doc,url,request,root,edit=true,onChange=()=>{},onBusy=()=>{},onDirty=()=>{}}){
        root.replaceChildren(node(doc,'p','Consultando alerta autorizada…'));let a;try{a=await request(url);}catch(e){root.replaceChildren(node(doc,'p',e.message));return;}
        root.replaceChildren(node(doc,'h4',a.active?'Alerta activa':'Sin alerta operativa activa'));
        if(a.reason){root.append(node(doc,'p',a.reason),node(doc,'small',(a.incident_on||'')+' · '+'Motivo registrado por '+(a.reason_by||'')+' · '+at(a.reason_at)));}
        root.append(node(doc,'p','Registra hechos observables y verificables. No incluyas insultos, clasificaciones discriminatorias ni diagnósticos. La alerta no modifica la atención ni los pagos.'));
        const form=node(doc,'div');form.className='patient-alert-form';const status=node(doc,'p');status.setAttribute('role','status');let saving=false;
        const field=(title,type='input')=>{const l=node(doc,'label',title),f=node(doc,type);f.setAttribute('aria-label',title);l.append(f);form.append(l);return f;};
        const reason=field(a.can_edit&&edit?'Hechos observables · motivo obligatorio':'Motivo de resolución','textarea');reason.maxLength=1000;reason.rows=4;
        let category,date,facts;
        if(a.can_edit&&edit){category=field('Categoría operativa','select');for(const[k,v]of Object.entries(a.categories)){const o=node(doc,'option',v);o.value=k;category.append(o);}category.value=a.category||Object.keys(a.categories)[0];date=field('Fecha del incidente');date.type='date';date.value=a.incident_on||new Intl.DateTimeFormat('en-CA',{timeZone:'America/Lima'}).format(new Date());facts=field('Confirmo que describo hechos observables, sin insultos ni diagnósticos.');facts.type='checkbox';reason.value=a.active?a.reason||'':'';}
        form.addEventListener('input',()=>onDirty(true));
        async function save(action){if(saving)return;if(reason.value.trim().length<15){status.textContent='Explica el motivo con al menos 15 caracteres.';reason.focus();return;}if(action==='save'&&!facts.checked){status.textContent='Confirma que registras hechos observables.';facts.focus();return;}if(!window.confirm(action==='resolve'?'¿Resolver esta alerta conservando todo su historial?':'¿Guardar esta alerta operativa del paciente con tu identidad y fecha?'))return;saving=true;onBusy(true);form.querySelectorAll('button,input,select,textarea').forEach(n=>n.disabled=true);try{await request(url,{method:'POST',body:JSON.stringify({request_key:crypto.randomUUID(),expected_version:a.version,expected_patient_id:a.patient_id,action,confirmed:true,reason:reason.value.trim(),...(action==='save'?{facts_confirmed:true,category:category.value,incident_on:date.value}:{})})});onDirty(false);await panel({doc,url,request,root,edit,onChange,onBusy,onDirty});onBusy(false);await onChange();}catch(e){status.textContent=e.message;}finally{saving=false;onBusy(false);form.querySelectorAll('button,input,select,textarea').forEach(n=>n.disabled=false);}}
        if(a.can_edit&&edit){const b=node(doc,'button','Guardar alerta');b.type='button';b.className='admission-btn admission-btn--primary';b.addEventListener('click',()=>save('save'));form.append(b);}
        if(a.can_resolve){const b=node(doc,'button','Resolver / desactivar alerta');b.type='button';b.className='admission-btn';b.addEventListener('click',()=>save('resolve'));form.append(b);}
        if(a.can_edit&&edit||a.can_resolve){form.append(status);root.append(form);}
        if(a.history.length){const h=node(doc,'details'),summary=node(doc,'summary','Auditoría de la alerta');h.append(summary);for(const e of a.history)h.append(node(doc,'p',({CREATED:'Creada',UPDATED:'Modificada',RESOLVED:'Resuelta'}[e.action]||e.action)+' · '+at(e.at)+' · '+e.actor));root.append(h);}
    }
    root.PatientAlerts={badge,panel};
}(window));
