(function(root,factory){const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else root.ProcedurePicker=api;}(typeof window!=='undefined'?window:globalThis,function(){
    'use strict';
    const fold=value=>String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('es');
    function open({doc=document,data,save,trigger}){
        if(doc.querySelector('.admission-procedure-dialog[open]'))return;
        const dialog=doc.createElement('dialog');dialog.className='admission-procedure-dialog';dialog.setAttribute('aria-labelledby','procedure-picker-title');
        const node=(tag,text,cls)=>{const n=doc.createElement(tag);if(text)n.textContent=text;if(cls)n.className=cls;return n;};
        const selected=new Set(data.items.map(p=>p.key)),customs=new Map(data.items.filter(p=>p.custom).map(p=>[p.key,{id:p.key.slice(2),label:p.label,family:p.family||'other'}]));
        const choices=new Map(Object.entries(data.catalog_entries||{}));for(const [key,value]of customs)choices.set(key,{...value,category:data.families[value.family]});
        let dirty=false,saving=false;
        const header=node('header'),title=node('h2','Solicitud de procedimientos');title.id='procedure-picker-title';header.append(title,node('p','Selecciona el catálogo aprobado o añade una solicitud específica. Solo se guarda al confirmar.'));
        const searchLabel=node('label','Buscar procedimientos'),search=node('input');search.type='search';search.placeholder='Nombre, categoría o término';searchLabel.append(search);
        const content=node('div',null,'procedure-picker-content'),list=node('div',null,'procedure-picker-catalog'),summary=node('aside',null,'procedure-picker-summary');summary.setAttribute('aria-live','polite');content.append(list,summary);
        const count=node('strong'),names=node('ul');summary.append(count,names);
        const specific=node('fieldset',null,'procedure-picker-specific');specific.append(node('legend','Solicitud específica del médico'));
        const label=node('label','Nombre específico'),text=node('input');text.maxLength=500;text.placeholder='Especifica el procedimiento requerido';label.append(text);
        const familyLabel=node('label','Categoría clínica'),family=node('select');family.setAttribute('aria-label','Categoría clínica');for(const [k,v]of Object.entries(data.families||{})){const o=node('option',v);o.value=k;family.append(o);}family.value='other';familyLabel.append(family);
        const add=node('button','Añadir solicitud específica');add.type='button';specific.append(label,familyLabel,add,node('small','La descripción debe corresponder a una indicación real. No define precios ni acredita realización.'));
        const status=node('p',null,'procedure-picker-status');status.setAttribute('role','alert');
        const footer=node('footer'),cancel=node('button','Cancelar'),confirm=node('button','Confirmar selección','primary');cancel.type=confirm.type='button';footer.append(cancel,confirm);
        dialog.append(header,searchLabel,content,specific,status,footer);doc.body.append(dialog);
        function update(){count.textContent=selected.size+' procedimientos seleccionados';names.replaceChildren(...Array.from(selected).map(key=>node('li',choices.get(key)?.label||key)));}
        function render(){list.replaceChildren();const groups=new Map();for(const[key,item]of choices){if(!fold(item.label+' '+item.category).includes(fold(search.value)))continue;const category=item.category||'Otros procedimientos';if(!groups.has(category)){const f=node('fieldset');f.append(node('legend',category));groups.set(category,f);list.append(f);}const line=node('label'),check=node('input');check.type='checkbox';check.checked=selected.has(key);check.dataset.procedureSelection=key;check.addEventListener('change',()=>{dirty=true;check.checked?selected.add(key):selected.delete(key);update();});line.append(check,node('span',item.label));groups.get(category).append(line);}if(!groups.size)list.append(node('p','Sin coincidencias. Cambia la búsqueda.'));update();}
        function close(){if(saving)return;if(dirty&&!window.confirm('¿Descartar la selección sin guardar?'))return;dialog.close();}
        function unload(e){if(dirty){e.preventDefault();e.returnValue='';}}
        window.addEventListener('beforeunload',unload);
        dialog.addEventListener('cancel',e=>{e.preventDefault();close();});dialog.addEventListener('close',()=>{window.removeEventListener('beforeunload',unload);dialog.remove();trigger?.focus();},{once:true});cancel.addEventListener('click',close);search.addEventListener('input',render);
        add.addEventListener('click',()=>{const value=text.value.trim();if(value.length<3){status.textContent='Escribe una solicitud específica de al menos 3 caracteres.';text.focus();return;}const id=crypto.randomUUID(),key='c:'+id,entry={id,label:value,family:family.value};customs.set(key,entry);choices.set(key,{...entry,category:data.families[family.value]});selected.add(key);dirty=true;text.value='';search.value='';status.textContent='';render();});
        confirm.addEventListener('click',async()=>{if(saving)return;saving=true;confirm.disabled=cancel.disabled=add.disabled=true;search.disabled=text.disabled=family.disabled=true;list.querySelectorAll('input').forEach(n=>n.disabled=true);status.textContent='Guardando selección…';try{const ok=await save({procedures:Array.from(selected).filter(k=>!k.startsWith('c:')),custom_procedures:Array.from(selected).filter(k=>customs.has(k)).map(k=>customs.get(k))},data.version);if(ok){dirty=false;dialog.close();return;}status.textContent='No se guardó. Revisa el aviso de la agenda. Si cambió la versión, cancela y vuelve a abrir con los datos actualizados.';}catch(e){status.textContent=e.message||'No se pudo guardar la selección.';}finally{saving=false;confirm.disabled=cancel.disabled=add.disabled=false;search.disabled=text.disabled=family.disabled=false;list.querySelectorAll('input').forEach(n=>n.disabled=false);}});
        render();dialog.showModal();search.focus();return dialog;
    }
    return {fold,open};
}));
