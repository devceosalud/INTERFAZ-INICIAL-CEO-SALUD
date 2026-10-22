(function(root){
    'use strict';
    const form=root.document?.querySelector('#user-doctor-link');if(!form)return;
    const status=root.document.querySelector('#user-doctor-link-status');
    let busy=false,key=null;
    form.addEventListener('input',()=>{if(!busy)key=null;});
    form.addEventListener('submit',async event=>{
        event.preventDefault();if(busy||!form.reportValidity())return;
        const selected=form.elements.doctor_id,reason=form.elements.reason.value.trim();
        if(reason.length<3){status.textContent='Indica el motivo.';return;}
        const next=selected.value?Number(selected.value):null,current=form.dataset.current?Number(form.dataset.current):null;
        if(next===current){status.textContent='No hay cambio de vínculo.';return;}
        const before=form.dataset.currentLabel,after=selected.selectedOptions[0].textContent.trim();
        if(!root.confirm('Revisar vinculación\nAnterior: '+before+'\nNuevo: '+after+'\nMotivo: '+reason+
            '\nSe invalidarán las sesiones de esta cuenta. ¿Confirmas la identidad y el cambio?'))return;
        key??=root.crypto.randomUUID();
        const data={doctor_id:next,expected_doctor_id:current,expected_version:Number(form.dataset.version),
            reason,identity_confirmed:true,confirmed:true,request_key:key};
        busy=true;form.querySelector('button').disabled=true;status.textContent='Guardando vinculación…';
        try{
            const response=await root.fetch(form.dataset.url,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json',
                'X-CSRF-TOKEN':root.document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify(data)});
            if(response.redirected||[401,419].includes(response.status))throw new Error('La sesión no está disponible. Inicia sesión nuevamente.');
            if(response.status>=500)throw new Error('No se pudo confirmar el resultado. Actualiza o reintenta con la misma operación.');
            const result=await response.json().catch(()=>{throw new Error('No se pudo confirmar el resultado. Actualiza antes de continuar.');});
            if(!response.ok){if(response.status===409)key=null;throw new Error([409,422].includes(response.status)?(Object.values(result.errors||{}).flat()[0]||result.message||'Revisa el cambio antes de continuar.'):'No tienes permiso para modificar la vinculación.');}
            status.textContent=result.message;root.location.reload();
        }catch(error){status.textContent=error.message||'No se confirmó el resultado. Actualiza o reintenta con la misma operación.';}
        finally{busy=false;form.querySelector('button').disabled=false;}
    });
})(typeof window==='undefined'?globalThis:window);
