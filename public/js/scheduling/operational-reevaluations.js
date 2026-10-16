(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else { root.OperationalReevaluations = api; document.addEventListener('DOMContentLoaded', () => api.start(document)); }
}(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict';
    const labels = {READY:'Listo para reevaluación', NOT_READY:'Dejó de estar listo', SUSPENDED:'Decisión manual suspendida · ningún realizado', WITHDRAWN:'Retirada', INACTIVE:'Cita inactiva / salida registrada'};
    function start(doc) {
        const panel = doc.querySelector('[data-operational-reevaluations]');
        if (!panel) return;
        const rows=panel.querySelector('[data-review-rows]'), status=panel.querySelector('[role="status"]');
        let timer=null, busy=false, stopped=false, sessionExpired=false;
        const node=(tag,text)=>{const n=doc.createElement(tag);if(text!==undefined)n.textContent=text;return n;};
        const time=value=>value?new Intl.DateTimeFormat('es-PE',{timeZone:'America/Lima',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false}).format(new Date(value)):'—';
        async function refresh() {
            if(busy||stopped||doc.hidden||sessionExpired)return;
            busy=true;
            try {
                const response=await fetch(panel.dataset.operationalReevaluations,{credentials:'same-origin',headers:{Accept:'application/json'}});
                if([401,419,403].includes(response.status)||response.redirected){sessionExpired=true;throw new Error('Sesión o permisos no disponibles. Vuelve a iniciar sesión.');}
                if(!response.ok)throw new Error('Actualización pendiente; se conservan los datos anteriores.');
                const data=await response.json();
                if(stopped||doc.hidden)return;
                const fragment=doc.createDocumentFragment();
                if(!data.rows.length)fragment.appendChild(node('p','Sin reevaluaciones registradas para hoy.'));
                for(const row of data.rows){
                    const card=node('article');card.className='operational-review-entry';
                    card.style.cssText='padding:12px;margin:8px 0;border:1px solid #cbd5e1;border-radius:8px;background:'+(row.review.status==='READY'?'#fed7aa':'#f1f5f9');
                    card.appendChild(node('strong',row.patient+' · '+(labels[row.review.status]||row.review.status)));
                    card.appendChild(node('p',row.doctor+' · '+row.service+' · Cita original '+row.appointment_time));
                    card.appendChild(node('p','Listo desde '+time(row.review.ready_at)+' · '+(row.review.origin==='MANUAL'?'Decisión manual':'Automática')));
                    const list=node('ul');for(const p of row.procedures)list.appendChild(node('li',p.label+' · '+(p.completed?'Realizado':'Pendiente')));card.appendChild(list);
                    if(row.review.status==='READY'){const link=node('a','Consultar procedimientos');link.href=panel.dataset.detailBase+'?appointment_id='+encodeURIComponent(row.id);card.appendChild(link);}
                    fragment.appendChild(card);
                }
                rows.replaceChildren(fragment);status.textContent='Actualizado · orden por hora de ingreso a reevaluación (Lima).';
            }catch(error){status.textContent=error.message;}finally{busy=false;arm();}
        }
        function arm(){if(timer!==null)clearTimeout(timer);timer=null;if(!stopped&&!doc.hidden&&!sessionExpired)timer=setTimeout(refresh,5000);}
        doc.addEventListener('visibilitychange',()=>{if(doc.hidden){clearTimeout(timer);timer=null;}else refresh();});
        globalThis.addEventListener?.('pagehide',()=>{stopped=true;clearTimeout(timer);});
        globalThis.addEventListener?.('pageshow',()=>{stopped=false;refresh();});
        refresh();
    }
    return {start,labels};
}));
