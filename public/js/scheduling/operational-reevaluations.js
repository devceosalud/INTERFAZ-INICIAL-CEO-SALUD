(function(root,factory){
    const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;
    else {root.OperationalReevaluations=api;document.addEventListener('DOMContentLoaded',()=>api.start(document));}
}(typeof window!=='undefined'?window:globalThis,function(){
    'use strict';
    const labels={READY:'Listo para reevaluación',NOT_READY:'Pendiente de procedimientos',WITHDRAWN:'Retirado',SUSPENDED:'Suspendido',INACTIVE:'Inactivo'};
    function groupRows(rows){const groups={active:[],pending:[],history:[]};for(const row of rows||[]){const group=row.review.status==='READY'?'active':row.review.status==='NOT_READY'&&row.procedures.length?'pending':'history';groups[group].push(row);}return groups;}
    function start(doc){for(const[instance,panel]of Array.from(doc.querySelectorAll('[data-operational-reevaluations]')).entries()){
        const rows=panel.querySelector('[data-review-rows]'),status=panel.querySelector('[role=status]');
        let selected='active',groups={active:[],pending:[],history:[]},busy=false,stopped=false,sessionExpired=false,timer=null,signature='';
        const node=(tag,text)=>{const n=doc.createElement(tag);if(text!==undefined)n.textContent=String(text);return n;};
        const time=value=>value?new Date(value).toLocaleTimeString('es-PE',{timeZone:'America/Lima',hour:'2-digit',minute:'2-digit',hour12:false}):'Sin ingreso previo';
        const buttons=Array.from(panel.querySelectorAll('[data-review-group]'));
        function render(){const fragment=doc.createDocumentFragment();for(const button of buttons){const active=button.dataset.reviewGroup===selected;button.setAttribute('aria-selected',String(active));button.tabIndex=active?0:-1;}
            rows.id='operational-review-rows-'+instance;rows.setAttribute('aria-labelledby','operational-review-tab-'+instance+'-'+selected);
            if(!groups[selected].length){const empty=node('p',({active:'No hay pacientes listos para reevaluación.',pending:'No hay procedimientos pendientes en la jornada.',history:'Sin retirados, suspendidos o inactivos en la jornada.'})[selected]);empty.className='operational-review-empty';fragment.appendChild(empty);}
            for(const row of groups[selected]){
                const card=node('article');card.className='operational-review-entry';card.dataset.ready=String(row.review.status==='READY');card.dataset.reviewStatus=row.review.status;card.dataset.reviewAppointment=row.id;
                const head=node('div');head.className='operational-review-entry-head';head.appendChild(node('h3',row.patient));
                const done=row.completed_count??row.procedures.filter(p=>p.completed).length,total=row.requested_count??row.procedures.length;
                const progress=node('span',total?done+' de '+total+' realizados':'Sin procedimientos');progress.className='operational-review-progress';head.appendChild(progress);card.appendChild(head);
                const state=node('p',labels[row.review.status]||row.review.status);state.className='operational-review-state';card.appendChild(state);card.appendChild(node('p',row.doctor+' · '+row.service));
                card.appendChild(node('small','Cita '+row.appointment_time+' · '+(row.review.status==='READY'?'Ingreso '+time(row.review.ready_at):'Fuera de la cola activa')));
                const pending=row.procedures.filter(p=>!p.completed).map(p=>p.label);if(pending.length)card.appendChild(node('p','Pendientes: '+pending.join(', ')));
                if(['MANUAL','MANUAL_DIRECT'].includes(row.review.origin))card.appendChild(node('small',(row.review.origin==='MANUAL_DIRECT'?'Marca manual directa · ':'Envío por procedimientos · ')+(row.review.authorized_by?.name||'Usuario no disponible')));
                else if(row.review.status==='READY')card.appendChild(node('small','Ingreso automático: todos los solicitados realizados.'));
                if(row.origins?.length>1)card.appendChild(node('small','Orígenes activos: '+row.origins.map(o=>({MANUAL_DIRECT:'marca directa',MANUAL_PARTIAL:'autorización parcial',AUTOMATIC:'procedimientos completos'}[o]||o)).join(' + ')));
                const link=node('a','Consultar reevaluación');link.href=panel.dataset.detailBase+'?appointment_id='+encodeURIComponent(row.id);
                link.addEventListener('click',event=>{if(doc.getElementById('admission-board')){event.preventDefault();doc.dispatchEvent(new CustomEvent('operational-queue-select',{detail:{id:Number(row.id)}}));}});card.appendChild(link);fragment.appendChild(card);
            }
            rows.replaceChildren(fragment);
        }
        for(const button of buttons){const key=button.dataset.reviewGroup;button.id='operational-review-tab-'+instance+'-'+key;button.setAttribute('aria-controls','operational-review-rows-'+instance);button.addEventListener('click',()=>{selected=key;render();});button.addEventListener('keydown',event=>{let index=buttons.indexOf(button);if(event.key==='ArrowRight')index=(index+1)%buttons.length;else if(event.key==='ArrowLeft')index=(index+buttons.length-1)%buttons.length;else return;event.preventDefault();selected=buttons[index].dataset.reviewGroup;render();buttons[index].focus();});}
        async function refresh(){if(busy||stopped||doc.hidden||sessionExpired)return;busy=true;try{
            const response=await fetch(panel.dataset.operationalReevaluations,{credentials:'same-origin',headers:{Accept:'application/json'}});
            if(response.redirected||[401,403,419].includes(response.status)){sessionExpired=true;throw new Error('Sesión o permisos no disponibles. Vuelve a iniciar sesión.');}
            if(!response.ok)throw new Error('Actualización pendiente; se conservan los datos anteriores.');const data=await response.json();if(stopped||doc.hidden)return;
            groups=data.groups||groupRows(data.rows);const next=JSON.stringify(groups);for(const[key,list]of Object.entries(groups))panel.querySelector('[data-review-count='+key+']').textContent=list.length;
            if(next!==signature){signature=next;render();}status.textContent='Jornada actual · orden por ingreso efectivo (Lima).';
            doc.dispatchEvent(new CustomEvent('operational-queue-updated',{detail:{active:groups.active.length,pending:groups.pending.length,history:groups.history.length}}));
        }catch(error){status.textContent=error.message;}finally{busy=false;arm();}}
        function arm(){if(timer!==null)clearTimeout(timer);timer=null;if(!stopped&&!doc.hidden&&!sessionExpired)timer=setTimeout(refresh,5000);}
        doc.addEventListener('visibilitychange',()=>{if(doc.hidden){clearTimeout(timer);timer=null;}else refresh();});
        globalThis.addEventListener?.('pagehide',()=>{stopped=true;clearTimeout(timer);});globalThis.addEventListener?.('pageshow',()=>{stopped=false;refresh();});refresh();
    }}
    return {start,labels,groupRows};
}));
