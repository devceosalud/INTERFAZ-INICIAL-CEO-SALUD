(function(root){
'use strict';
const form=document.getElementById('formCreateAppointment'),modal=document.getElementById('appointmentModalCreate');
const input=document.getElementById('fileInput'),drop=document.getElementById('dropZone');
if(!form || !modal || !input)return;
const field=id=>modal.querySelector('#'+id);
input.accept='image/jpeg,image/png,application/pdf';input.multiple=true;input.removeAttribute('name');
const oldPreview=document.getElementById('preview');if(oldPreview)oldPreview.style.display='none';
const debug=document.getElementById('debugTexto');if(debug){debug.textContent='';debug.hidden=true;const details=debug.closest('details');if(details)details.hidden=true;}
const result=document.getElementById('resultado');if(result)result.hidden=true;
const oldStatus=document.getElementById('estado');if(oldStatus)oldStatus.textContent='Comprobantes privados: revisa los datos antes de registrar.';
const host=document.createElement('div');host.id='legacy-receipt-review';(drop||input).insertAdjacentElement('afterend',host);
if(!root.AppointmentReceiptReview){
 root.LegacyReceiptReview={append(){if(input.files.length)throw new Error('No se cargó la revisión de comprobantes. Actualiza el formulario o registra manualmente sin imagen.');}};
 return;
}
const receipt=root.AppointmentReceiptReview({host,input,prefix:'legacy-receipt',
 paymentFields:{amount:field('total_pagado'),method:field('metodo_pago'),operation:field('numero_operacion'),origin:field('entidad_origen')}});
root.LegacyReceiptReview={append(body){
 const payload={};receipt.append(body,payload);
 if(payload.proof_review)for(const [key,value]of Object.entries(payload.proof_review))if(value!==null)body.append('proof_review['+key+']',key==='reviewed'?'1':String(value));
},reset:()=>receipt.reset()};
if(drop){
 drop.addEventListener('click',e=>{if(e.target!==input)input.click();});
 drop.addEventListener('dragover',e=>{e.preventDefault();drop.classList.add('dragover');});
 drop.addEventListener('dragleave',()=>drop.classList.remove('dragover'));
 drop.addEventListener('drop',e=>{e.preventDefault();drop.classList.remove('dragover');input.files=e.dataTransfer.files;input.dispatchEvent(new Event('change',{bubbles:true}));});
}
form.addEventListener('reset',()=>{void receipt.reset();});
if(root.jQuery)root.jQuery(modal).on('hidden.bs.modal',()=>{void receipt.reset();});
}(typeof window!=='undefined'?window:globalThis));
