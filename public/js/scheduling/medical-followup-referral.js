(function(root,factory){const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else root.MedicalFollowupReferral=api;}(typeof window!=='undefined'?window:globalThis,function(){
    'use strict';const requests=new Map(),pending=new Set();
    async function open({data,save,trigger,onSaved,onError}){
        if(!data?.items?.length)return false;
        const payload={expected_patient_id:data.patient_id,expected_procedure_version:data.expected_procedure_version};
        const signature=JSON.stringify([data.url,payload]);if(pending.has(signature))return false;
        if(!requests.has(signature))requests.set(signature,globalThis.crypto.randomUUID());payload.request_key=requests.get(signature);
        pending.add(signature);if(trigger){trigger.disabled=true;trigger.setAttribute('aria-busy','true');}
        try{const result=await save(payload);await onSaved?.(result);requests.delete(signature);return true;}
        catch(error){onError?.(error);return false;}
        finally{pending.delete(signature);if(trigger){trigger.disabled=false;trigger.removeAttribute('aria-busy');}}
    }
    return {open};
}));
