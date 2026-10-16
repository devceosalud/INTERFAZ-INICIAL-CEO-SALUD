(function(root){
    'use strict';
    function messages(body){return Object.entries(body.errors||body.error||{}).flatMap(([key,values])=>(Array.isArray(values)?values:[values]).map(message=>({key,message:String(message)})));}
    async function request(url,options={}){
        const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
        const response=await fetch(url,{credentials:'same-origin',...options,headers:{Accept:'application/json','X-CSRF-TOKEN':csrf,...options.headers}});
        let body;try{body=await response.json();}catch(_){throw new Error('El servidor no devolvió una respuesta válida. Conserva los datos y vuelve a intentar.');}
        if(!response.ok||body.code===0||body.code===2){const e=new Error(messages(body)[0]?.message||body.message||body.msg||'No se pudo guardar el catálogo.');e.body=body;throw e;}
        return body;
    }
    function errors(form,error){
        form.querySelectorAll('[aria-invalid="true"]').forEach(f=>{f.setAttribute('aria-invalid','false');f.classList.remove('is-invalid');});
        let box=form.querySelector('[data-catalog-errors]');if(!box){box=document.createElement('div');box.className='alert alert-danger';box.dataset.catalogErrors='';box.setAttribute('role','alert');box.tabIndex=-1;form.querySelector('.modal-body')?.prepend(box);}
        box.hidden=!error;if(!error){box.textContent='';return;}
        const list=messages(error.body||{});box.textContent=list.length?list.map(e=>e.message).join(' '):error.message;
        let first=null;list.forEach(({key})=>{const field=form.elements.namedItem(key)||form.elements.namedItem(key+'_edit');if(field&&field.type!=='hidden'){field.setAttribute('aria-invalid','true');field.classList.add('is-invalid');first=first||field;}});
        const focus=first||box;focus.scrollIntoView?.({block:'nearest'});focus.focus();
    }
    function bind(form){if(!form)return;Array.from(form.elements).forEach(field=>{if(!field.name||field.type==='hidden')return;if(!field.id)field.id=form.id+'-'+field.name;const label=field.parentElement?.querySelector('label');if(label)label.htmlFor=field.id;});form.addEventListener('submit',async e=>{
        e.preventDefault();errors(form,null);const buttons=Array.from(form.querySelectorAll('[type="submit"]'));buttons.forEach(b=>b.disabled=true);
        const payload=new FormData(form);form.querySelectorAll('[data-explicit-boolean]').forEach(f=>payload.set(f.name,f.checked?'1':'0'));
        try{const result=await request(form.action,{method:'POST',body:payload});root.sessionStorage.setItem('catalogSaved',result.msg||'Cambios guardados.');root.location.reload();}
        catch(error){errors(form,error);}
        finally{buttons.forEach(b=>b.disabled=false);}
    });}
    function show(modal){if(root.bootstrap?.Modal)root.bootstrap.Modal.getOrCreateInstance(modal).show();else root.jQuery(modal).modal('show');}
    function fill(form,values){Object.entries(values).forEach(([name,value])=>{const field=form.elements.namedItem(name)||form.elements.namedItem(name+'[]');if(field?.multiple){const selected=(value||[]).map(String);Array.from(field.options).forEach(o=>o.selected=selected.includes(o.value));}else if(field?.type==='checkbox')field.checked=Boolean(value);else if(field)field.value=value??'';});}
    async function deactivate(url,id,version){
        const answer=await root.Swal.fire({title:'Desactivar registro',text:'Se conserva el historial y no se recalculan citas existentes.',icon:'warning',showCancelButton:true,confirmButtonText:'Desactivar',cancelButtonText:'Cancelar'});
        if(!answer.isConfirmed)return;try{await request(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,catalog_version:version})});root.location.reload();}
        catch(e){await root.Swal.fire({icon:'error',text:e.message});}
    }
    if(root.document)root.addEventListener('DOMContentLoaded',()=>{const saved=root.sessionStorage.getItem('catalogSaved');if(saved){root.sessionStorage.removeItem('catalogSaved');const box=document.createElement('div');box.className='alert alert-success';box.setAttribute('role','status');box.textContent=saved;document.querySelector('.card-body')?.prepend(box);}});
    const api={messages,request,errors,bind,show,fill,deactivate};root.CatalogEditor=api;if(typeof module==='object'&&module.exports)module.exports=api;
})(typeof window!=='undefined'?window:globalThis);
