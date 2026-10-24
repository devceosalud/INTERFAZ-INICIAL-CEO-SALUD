(function(root,factory){const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else root.ProcedurePicker=api;}(typeof window!=='undefined'?window:globalThis,function(){
    'use strict';
    const fold=value=>String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('es').replace(/\s+/g,' ').trim();
    const groups={surgery:'Cirugía / Operación',mri:'Resonancia magnética',radiography:'Radiografías',ultrasound:'Ecografías',laboratory:'Laboratorio',wound_care:'Curaciones y pie diabético',infiltration:'Infiltraciones y procedimientos',other:'Otros procedimientos'};
    function category(key,item){if(key==='ultrasound'||key.startsWith('us_'))return 'ultrasound';if(['prp','hyaluronic_acid'].includes(key)||key.startsWith('order_'))return 'infiltration';return groups[item.family]?item.family:'other';}
    function matches(term,key,item){term=fold(term);if(!term)return true;if(['operacion','cirugia'].includes(term))return item.family==='surgery';if(['rm','rmn','resonancia','resonancia magnetica'].includes(term))return item.family==='mri';return fold(item.label+' '+item.category+' '+(groups[category(key,item)]||'')).includes(term);}
    function open({doc=document,data,save,trigger}){
        if(doc.querySelector('.admission-procedure-dialog[open]'))return;
        const win=doc.defaultView||window,node=(tag,text,cls)=>{const n=doc.createElement(tag);if(text!==undefined&&text!==null)n.textContent=text;if(cls)n.className=cls;return n;};
        const dialog=node('dialog',null,'admission-procedure-dialog procedure-picker-advanced');dialog.setAttribute('aria-labelledby','procedure-picker-title');
        const selected=new Set(data.items.map(p=>p.key)),customs=new Map(data.items.filter(p=>p.custom).map(p=>[p.key,{id:p.key.slice(2),label:p.label,family:p.family||'other',...(p.reusable_id?{reusable_id:p.reusable_id}:{})}]));
        const confirmedKeys=new Set(data.items.map(p=>p.key)),choices=new Map(Object.entries(data.catalog_entries||{}));
        const remember=p=>{const key='c:'+p.id;if(!choices.has(key))choices.set(key,{label:p.label,family:p.family,category:groups[p.family]||groups.other,personal:true,reusable_id:p.id});};
        (data.personal_suggestions||[]).forEach(remember);for(const[key,p]of customs)choices.set(key,{...p,category:groups[p.family]||groups.other,personal:true,reusable_id:p.reusable_id});
        let dirty=false,saving=false,activeCategory='radiography',searchEpoch=0,searchTimer,searchAbort,matching=[];
        const orderFields=win.ClinicalOrderFields.create({doc,data,selected,onDirty:()=>{dirty=true;},onUpdate:()=>update()});
        const header=node('header'),title=node('h2','SOLICITUD DE PROCEDIMIENTOS');title.id='procedure-picker-title';header.append(title,node('p','Busca, selecciona o agrega una solicitud personal. Solo se guarda al confirmar.'));
        const body=node('div',null,'procedure-picker-body'),searchArea=node('div',null,'procedure-picker-search-area'),searchLabel=node('label','Buscar procedimientos'),search=node('input');search.type='search';search.placeholder='Procedimiento, operación, RM o nombre utilizado antes';search.autocomplete='off';search.setAttribute('aria-controls','procedure-picker-results');searchLabel.append(search);searchArea.append(searchLabel);
        const quickCount=node('small','Seleccionados: 0','procedure-picker-quick-count');searchArea.append(quickCount);
        const priorities=node('div',null,'procedure-picker-priorities');priorities.setAttribute('aria-label','Procedimientos prioritarios');
        const bench=node('div',null,'procedure-picker-workbench'),nav=node('nav',null,'procedure-picker-categories'),list=node('div',null,'procedure-picker-catalog'),summary=node('aside',null,'procedure-picker-summary');nav.setAttribute('aria-label','Categorías de procedimientos');list.id='procedure-picker-results';bench.append(nav,list,summary);
        const count=node('strong'),names=node('ul');count.setAttribute('aria-live','polite');summary.append(count,names);
        const addRow=node('div',null,'procedure-picker-inline-add'),add=node('button'),family=node('select');family.setAttribute('aria-label','Categoría del procedimiento nuevo');for(const[k,v]of Object.entries(groups))family.append(new Option(v,k));family.value='other';add.type='button';addRow.append(add,family);searchArea.append(addRow);
        const status=node('p',null,'procedure-picker-status');status.setAttribute('role','status');status.setAttribute('aria-live','polite');
        const privacy=node('small','Las solicitudes personales son privadas del médico. Escribe solo el procedimiento, sin datos identificables del paciente. No se incorporan al catálogo oficial ni crean un cobro.','procedure-picker-privacy');
        const footer=node('footer'),cancel=node('button','Cancelar'),confirm=node('button','Confirmar selección','primary');cancel.type=confirm.type='button';footer.append(cancel,confirm);
        body.append(orderFields.header,searchArea,priorities,bench,orderFields.notes,privacy,status);dialog.append(header,body,footer);doc.body.append(dialog);
        function toggle(key,checked){checked?selected.add(key):selected.delete(key);const item=choices.get(key);if(checked&&key.startsWith('c:')&&!customs.has(key))customs.set(key,{id:key.slice(2),label:item.label,family:item.family,reusable_id:item.reusable_id||key.slice(2)});dirty=true;render();}
        function update(){
            quickCount.textContent='Seleccionados: '+selected.size;count.textContent=selected.size+' procedimientos seleccionados';names.replaceChildren();
            for(const key of selected){const item=choices.get(key),li=node('li'),label=node('span',(item?.label||key)+(orderFields.description(key)?' · '+orderFields.description(key):''));li.append(label);
                const remove=node('button','Quitar');remove.type='button';remove.setAttribute('aria-label','Quitar '+(item?.label||key));remove.addEventListener('click',()=>toggle(key,false));li.append(remove);
                if(key.startsWith('c:')&&!confirmedKeys.has(key)&&!item?.reusable_id){const edit=node('button','Corregir');edit.type='button';edit.addEventListener('click',()=>{const text=node('input');text.value=item.label;text.maxLength=500;text.setAttribute('aria-label','Corregir nombre del procedimiento');const f=node('select');f.setAttribute('aria-label','Corregir categoría del procedimiento');for(const[k,v]of Object.entries(groups))f.append(new Option(v,k));f.value=item.family;const done=node('button','Aplicar');done.type='button';done.addEventListener('click',()=>{if(text.value.trim().length<3){text.focus();return;}item.label=text.value.trim();item.family=f.value;item.category=groups[f.value];Object.assign(customs.get(key),{label:item.label,family:f.value});dirty=true;render();});li.replaceChildren(text,f,done);text.focus();});li.append(edit);}
                names.append(li);
            }
        }
        function option(key,item){const line=node('div',null,'procedure-picker-option'),label=node('label'),check=node('input');check.type='checkbox';check.checked=selected.has(key);check.dataset.procedureSelection=key;check.addEventListener('change',()=>toggle(key,check.checked));label.append(check,node('span',item.label));if(item.personal)label.append(node('small','Personal','procedure-picker-personal'));line.append(label);const options=orderFields.options(key,item);if(options)line.append(options);return line;}
        function render(){
            const scroll=list.scrollTop;const query=fold(search.value);matching=[...choices].filter(([key,item])=>matches(query,key,item));
            nav.replaceChildren();for(const[key,label]of Object.entries(groups)){const b=node('button',label);b.type='button';b.dataset.procedureCategory=key;b.setAttribute('aria-pressed',String(!query&&activeCategory===key));b.addEventListener('click',()=>{search.value='';activeCategory=key;searchEpoch++;searchAbort?.abort();render();});nav.append(b);}
            const recent=node('button','Mis procedimientos');recent.type='button';recent.dataset.procedureCategory='personal';recent.setAttribute('aria-pressed',String(!query&&activeCategory==='personal'));recent.addEventListener('click',()=>{search.value='';activeCategory='personal';render();});nav.append(recent);
            priorities.replaceChildren();for(const key of ['surgery','mri'])if(choices.has(key))priorities.append(option(key,choices.get(key)));
            list.replaceChildren();const heading=node('h3',query?'Resultados para «'+search.value.trim()+'»':activeCategory==='personal'?'Mis procedimientos · privados':groups[activeCategory]);list.append(heading);
            const visible=matching.filter(([key,item])=>!['surgery','mri'].includes(key)&&(query||(activeCategory==='personal'?item.personal:category(key,item)===activeCategory)));
            const grid=node('div',null,'procedure-picker-options-grid');for(const[key,item]of visible)grid.append(option(key,item));list.append(grid);
            if(!visible.length)list.append(node('p',!query&&['surgery','mri'].includes(activeCategory)?'Selecciona el procedimiento en los accesos prioritarios superiores.':'Sin coincidencias en esta categoría. Busca en todo el catálogo o agrega una solicitud personal.','procedure-picker-empty'));
            const exact=[...choices].some(([key,item])=>fold(item.label)===query||(['operacion','cirugia'].includes(query)&&key==='surgery')||(['rm','rmn','resonancia','resonancia magnetica'].includes(query)&&key==='mri'));
            addRow.hidden=!query||exact;add.textContent='+ Agregar «'+search.value.trim()+'»';
            update();list.scrollTop=scroll;
        }
        function addSearch(){clearTimeout(searchTimer);searchAbort?.abort();searchEpoch++;const value=search.value.trim();if(value.length<3){status.textContent='Escribe al menos3 caracteres para el procedimiento.';search.focus();return;}
            const exact=[...choices].find(([key,item])=>fold(item.label)===fold(value));if(exact){toggle(exact[0],true);return;}
            const id=crypto.randomUUID(),key='c:'+id,item={id,label:value,family:family.value,category:groups[family.value],personal:true};customs.set(key,{id,label:value,family:family.value});choices.set(key,item);selected.add(key);dirty=true;search.value='';activeCategory='personal';status.textContent='Solicitud agregada al borrador. Confirma para guardarla.';render();search.focus();}
        add.addEventListener('click',addSearch);
        search.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();if(matching.length)toggle(matching[0][0],true);else addSearch();}else if(e.key==='ArrowDown'){e.preventDefault();const target=matching[0]?.[0];if(target)dialog.querySelector('[data-procedure-selection="'+CSS.escape(target)+'"]')?.focus();}});
        search.addEventListener('input',()=>{
            const value=fold(search.value);family.value=/^radiografia(?:\s|$)/.test(value)?'radiography':/^ecografia(?:\s|$)/.test(value)?'ultrasound':/^resonancia(?:\s|$)/.test(value)?'mri':/^cirugia(?:\s|$)/.test(value)?'surgery':'other';render();clearTimeout(searchTimer);searchAbort?.abort();const epoch=++searchEpoch;
            if(value.length<2||!data.personal_search_url)return;
            searchTimer=win.setTimeout(async()=>{searchAbort=new AbortController();try{const r=await win.fetch(data.personal_search_url,{method:'POST',credentials:'same-origin',signal:searchAbort.signal,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':doc.querySelector('meta[name=csrf-token]').content},body:JSON.stringify({query:search.value.trim()})});if(!r.ok)throw new Error('No se pudieron consultar las sugerencias personales.');const result=await r.json();if(epoch!==searchEpoch||!dialog.open)return;result.items.forEach(remember);render();}catch(e){if(e.name!=='AbortError'&&epoch===searchEpoch)status.textContent=e.message;}},200);
        });
        function close(){if(saving)return;if(dirty&&!win.confirm('¿Descartar la selección sin guardar?'))return;dialog.close();}
        function unload(e){if(dirty){e.preventDefault();e.returnValue='';}}
        win.addEventListener('beforeunload',unload);dialog.addEventListener('keydown',e=>{if(e.key==='Escape'&&!e.isComposing){e.preventDefault();e.stopPropagation();if(!e.repeat)close();}});dialog.addEventListener('cancel',e=>{e.preventDefault();close();});dialog.addEventListener('close',()=>{clearTimeout(searchTimer);searchAbort?.abort();win.removeEventListener('beforeunload',unload);dialog.remove();(trigger?.isConnected?trigger:doc.querySelector('[data-select-procedures]'))?.focus();},{once:true});cancel.addEventListener('click',close);
        confirm.addEventListener('click',async()=>{if(saving||!orderFields.validate())return;saving=true;dialog.querySelectorAll('input,textarea,select,button').forEach(n=>n.disabled=true);status.textContent='Guardando selección…';
            try{const ok=await save({...orderFields.values(),procedures:[...selected].filter(k=>!k.startsWith('c:')),custom_procedures:[...selected].filter(k=>customs.has(k)).map(k=>customs.get(k))},data.version);if(ok){dirty=false;dialog.close();return;}status.textContent='No se guardó. Si cambió la versión, cancela y vuelve a abrir con los datos actualizados.';}catch(e){status.textContent=e.message||'No se pudo guardar.';}finally{saving=false;dialog.querySelectorAll('input,textarea,select,button').forEach(n=>n.disabled=false);}});
        render();dialog.showModal();search.focus();return dialog;
    }
    return {fold,matches,category,open};
}));
