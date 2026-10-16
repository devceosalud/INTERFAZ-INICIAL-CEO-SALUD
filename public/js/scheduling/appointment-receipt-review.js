(function(root){
'use strict';
root.AppointmentReceiptReview=function(config){
    const host=config.host,input=config.input,fields=config.paymentFields;
    if(!host || !input) return {append(){},reset(){},hasChanges(){return false;}};
    const engine=root.AppointmentReceiptOCR.createEngine({basePath:config.basePath});
    let version=0,objectUrl=null,source='MANUAL',suggestions=null,busy=false;
    const make=(tag,text)=>{const e=document.createElement(tag);if(text)e.textContent=text;return e;};
    const status=make('p','Adjunta imagen JPG o PNG para identificar monto y operación.');status.setAttribute('role','status');
    const selector=make('select');selector.setAttribute('aria-label','Comprobante para leer');
    const readButton=make('button','Leer imagen con OCR');readButton.type='button';readButton.className='agenda-btn';readButton.disabled=true;
    const cancelButton=make('button','Detener OCR / completar manualmente');cancelButton.type='button';cancelButton.className='agenda-btn';cancelButton.disabled=true;
    const preview=make('img');preview.alt='Vista previa privada del comprobante seleccionado';preview.hidden=true;preview.style.maxWidth='100%';preview.style.maxHeight='280px';
    const suggestionBox=make('div');suggestionBox.hidden=true;
    const suggestionFields={},suggestionLabels={};
    for(const [key,label] of Object.entries({amount:'Monto sugerido',operation:'N.º de operación sugerido',bank:'Entidad sugerida',date:'Fecha sugerida',time:'Hora sugerida'})){
        const wrapper=make('label',label);wrapper.className='agenda-field';const field=make('input');field.className='agenda-field__input';field.setAttribute('aria-label',label);
        wrapper.append(field);suggestionBox.append(wrapper);suggestionFields[key]=field;suggestionLabels[key]=wrapper;
    }
    const applyButton=make('button','Usar sugerencias en el pago');applyButton.type='button';applyButton.className='agenda-btn';suggestionBox.append(applyButton);
    const reviewLabel=make('label');const reviewed=make('input');reviewed.type='checkbox';reviewed.id=config.prefix+'-proof-reviewed';
    reviewLabel.append(reviewed,document.createTextNode(' Verifiqué el monto, la operación y todos los comprobantes. Confirmo el registro manual del pago.'));
    const dateLabel=make('label','Fecha del comprobante (opcional)');dateLabel.className='agenda-field';const date=make('input');date.type='date';date.id=config.prefix+'-receipt-date';date.className='agenda-field__input';dateLabel.append(date);
    const timeLabel=make('label','Hora del comprobante (opcional)');timeLabel.className='agenda-field';const time=make('input');time.type='time';time.id=config.prefix+'-receipt-time';time.className='agenda-field__input';timeLabel.append(time);
    const heading=make('h4','Leer importe y N.º de operación');
    heading.className='agenda-receipt-heading';
    const buttons=make('div');buttons.className='agenda-receipt-actions';
    buttons.append(selector,readButton,cancelButton);
    cancelButton.hidden=true;
    const primary=make('div');primary.className='agenda-receipt-primary';
    primary.append(suggestionLabels.amount,suggestionLabels.operation);
    const additional=make('details');additional.className='agenda-receipt-secondary';
    const extraLabel=make('summary','Otros datos extraídos · opcional');
    additional.append(extraLabel,suggestionLabels.bank,suggestionLabels.date,suggestionLabels.time);
    const receiptActions=make('div');receiptActions.className='agenda-receipt-actions';
    receiptActions.append(applyButton);
    suggestionBox.append(primary,additional,receiptActions);
    const extras=make('details');extras.className='agenda-receipt-secondary';
    extras.append(make('summary','Fecha y hora del comprobante · opcional'),dateLabel,timeLabel);
    host.append(heading,buttons,status,preview,suggestionBox,extras,reviewLabel);
    function invalidate(){reviewed.checked=false;}
    function releasePreview(){if(objectUrl){URL.revokeObjectURL(objectUrl);objectUrl=null;}preview.hidden=true;preview.removeAttribute('src');}
    function refreshSelect(field){if(root.jQuery?.fn?.selectpicker)root.jQuery(field).selectpicker('refresh');}
    function showPreview(){
        refreshSelect(selector);
        releasePreview();const file=input.files[Number(selector.value)||0];
        readButton.disabled=!file || !['image/png','image/jpeg'].includes(file.type) || fields.amount.disabled || busy;
        cancelButton.disabled=!file;
        cancelButton.hidden=!busy;
        if(file && ['image/png','image/jpeg'].includes(file.type)){objectUrl=URL.createObjectURL(file);preview.src=objectUrl;preview.hidden=false;}
    }
    function validate(){
        const files=Array.from(input.files||[]);
        if(files.length>5 || files.reduce((n,f)=>n+f.size,0)>20*1024*1024) throw new Error('Máximo 5 comprobantes y 20 MB en total.');
        if(files.some(file=>root.AgendaGuidance.invalidProof(file)))throw new Error(root.AgendaGuidance.copy.fileInvalid);
        return files;
    }
    async function reset(clearInput=true){
        ++version;await engine.cancel();busy=false;source='MANUAL';suggestions=null;invalidate();date.value='';time.value='';suggestionBox.hidden=true;
        Object.values(suggestionFields).forEach(f=>f.value='');if(clearInput)input.value='';selector.replaceChildren();releasePreview();readButton.disabled=true;
        status.textContent='Adjunta una imagen JPG o PNG para sugerir monto y operación.';cancelButton.disabled=true;cancelButton.hidden=true;
    }
    input.addEventListener('change',async()=>{
        await reset(false);
        try{validate().forEach((file,index)=>{const option=make('option',file.name);option.value=String(index);selector.append(option);});
            showPreview();status.textContent='Revisa todos los archivos antes del registro. Los PDF se completan manualmente.';
        }catch(e){status.textContent=e.message;input.value='';}
    });
    selector.addEventListener('change',async()=>{++version;await engine.cancel();busy=false;invalidate();suggestionBox.hidden=true;showPreview();});
    readButton.addEventListener('click',async()=>{
        const file=input.files[Number(selector.value)||0];if(!file || busy)return;
        const current=++version;busy=true;invalidate();readButton.disabled=true;cancelButton.hidden=false;suggestionBox.hidden=true;
        try{
            const result=await engine.recognize(file,p=>{if(current===version)status.textContent='Leyendo imagen localmente: '+Math.round(p*100)+'%';});
            if(current!==version)return;suggestions=result;
            Object.keys(suggestionFields).forEach(k=>suggestionFields[k].value=result[k]||'');suggestionBox.hidden=false;
            status.textContent=result.ambiguous
                ? 'Hay varios importes u operaciones posibles. Completa y verifica los datos manualmente.'
                : 'Revisa el monto y N.º de operación sugeridos antes de aplicarlos. OCR no confirma pagos.';
        }catch(e){if(current===version)status.textContent=e.message;}
        finally{if(current===version){busy=false;showPreview();}}
    });
    cancelButton.addEventListener('click',async()=>{
        ++version;await engine.cancel();busy=false;source='MANUAL';suggestions=null;suggestionBox.hidden=true;invalidate();showPreview();
        status.textContent='Lectura detenida. Completa y revisa los datos manualmente; conserva los archivos seleccionados.';
    });
    applyButton.addEventListener('click',()=>{
        if(!suggestions)return;
        const bank=suggestionFields.bank.value.trim().toUpperCase();
        // Ambiguous OCR values are never copied without explicit human correction.
        if(suggestionFields.amount.value)fields.amount.value=suggestionFields.amount.value;
        if(suggestionFields.operation.value)fields.operation.value=suggestionFields.operation.value;
        if(['YAPE','PLIN'].includes(bank))fields.method.value=bank;
        else if(bank)fields.method.value='TRANSFERENCIA';
        if(bank)fields.origin.value=bank;
        date.value=suggestionFields.date.value;time.value=suggestionFields.time.value;
        Object.values(fields).forEach(f=>{f.dispatchEvent(new Event('input',{bubbles:true}));f.dispatchEvent(new Event('change',{bubbles:true}));});
        refreshSelect(fields.method);
        source='BROWSER_OCR';invalidate();status.textContent='Sugerencias copiadas. Verifica los campos del pago y marca la revisión antes de registrar.';
    });
    [...Object.values(fields),date,time].forEach(f=>f.addEventListener('input',invalidate));
    function append(body,payload){
        const files=validate();if(!files.length)return;
        if(busy)throw new Error('Espera la lectura o descártala antes de registrar.');
        if(!reviewed.checked)throw new Error('Revisa todos los comprobantes y confirma la revisión humana antes de registrar el pago.');
        payload.proof_review={reviewed:true,source,date:date.value||null,time:time.value||null};
        files.forEach(file=>body.append('proofs[]',file));
    }
    return {append,reset,hasChanges:()=>busy || Boolean(input.files.length)};
};
}(typeof window!=='undefined'?window:globalThis));
