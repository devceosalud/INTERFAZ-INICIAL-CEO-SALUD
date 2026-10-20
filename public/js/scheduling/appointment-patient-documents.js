(function(root,factory){const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else root.AppointmentPatientDocuments=api;}(typeof window!=='undefined'?window:globalThis,function(){
'use strict';
let pdfModule=null;
function pdfOptions(bytes){return {data:bytes,isEvalSupported:false,enableXfa:false,stopAtErrors:true,maxImageSize:16000000,canvasMaxAreaInBytes:16777216,disableAutoFetch:true,disableRange:true,disableStream:true,useSystemFonts:true};}
function create({doc=document,url,appointmentId,initialData=null,readResponse,onDirty=()=>{},onBusy=()=>{},onUploaded=()=>{}}){
 const node=(tag,text)=>{const e=doc.createElement(tag);if(text)e.textContent=text;return e;};
 const area=node('section');area.className='patient-appointment-documents clarity-mask';area.dataset.clarityMask='true';
 area.appendChild(node('h4','Documentos de la cita'));
 const previewDisposers=new Set();const disposePreviews=()=>{for(const dispose of previewDisposers)dispose();previewDisposers.clear();};
 const view=doc.defaultView;if(view?.MutationObserver){let mounted=false;const observer=new view.MutationObserver(()=>{if(area.isConnected)mounted=true;else if(mounted){disposePreviews();observer.disconnect();}});observer.observe(doc.body,{childList:true,subtree:true});}
 view?.addEventListener('pagehide',disposePreviews,{once:true});
 const list=node('div'),status=node('p');status.setAttribute('role','status');const form=node('form');form.hidden=true;form.className='patient-document-upload';
 const label=node('input');label.maxLength=120;label.required=true;label.setAttribute('aria-label','Nombre del documento de la cita');label.placeholder='Ej.: Resonancia aportada por el paciente';
 const kind=node('select');kind.setAttribute('aria-label','Tipo de documento de la cita');for(const[value,text]of Object.entries({REPORT:'Informe / resultado',PRESCRIPTION:'Receta aportada',ADMINISTRATIVE:'Otro documento aportado'})){const o=node('option',text);o.value=value;kind.appendChild(o);}
 const file=node('input');file.type='file';file.accept='.jpg,.jpeg,.png,.pdf';file.required=true;file.setAttribute('aria-label','Archivo del paciente');
 const reason=node('input');reason.hidden=true;reason.maxLength=500;reason.placeholder='Motivo del reemplazo';reason.setAttribute('aria-label','Motivo del reemplazo documental');
 const submit=node('button','Adjuntar documento');submit.type='submit';submit.className='admission-btn admission-btn--primary agenda-btn';
 const add=node('button','+ Adjuntar archivo');add.type='button';add.className='admission-btn admission-btn--primary agenda-btn';add.hidden=true;add.setAttribute('aria-expanded','false');
 const cancel=node('button','Cancelar');cancel.type='button';cancel.className='admission-btn agenda-btn';
 const field=(text,input)=>{const l=node('label',text);l.appendChild(input);return l;};
 form.append(field('Nombre / etiqueta',label),field('Tipo de documento',kind),field('Seleccionar archivo · PDF, JPG o PNG, hasta 8 MB',file),reason,submit,cancel);area.append(list,add,form,status);
 let loaded=false,loading=null,busy=false,key=null,replacement=null;
 const dirty=()=>onDirty(Boolean(label.value||file.files.length||replacement));
 [label,kind,file,reason].forEach(e=>e.addEventListener('input',()=>{key=null;dirty();}));
 function reset(){label.value='';file.value='';reason.value='';reason.hidden=true;reason.required=false;replacement=null;key=null;form.hidden=true;add.setAttribute('aria-expanded','false');submit.textContent='Adjuntar documento';dirty();}
 function openForm(){form.hidden=false;add.setAttribute('aria-expanded','true');label.focus();}
 add.addEventListener('click',()=>{if(!busy)openForm();});
 cancel.addEventListener('click',()=>{if(!busy){reset();add.focus();}});
 const headers=()=>({Accept:'application/json','X-CSRF-TOKEN':doc.querySelector('meta[name="csrf-token"]')?.content||''});
 async function load(force=false){if(loading)return loading;if(loaded&&!force)return;
  loading=(async()=>{try{const data=initialData&&!force?initialData:await readResponse(await fetch(url,{credentials:'same-origin',headers:headers()}));initialData=null;disposePreviews();list.replaceChildren();add.hidden=!data.can_upload_patient;if(!data.can_upload_patient)form.hidden=true;
   if(!data.can_view_patient)list.appendChild(node('p','Acceso restringido a documentos aportados por el paciente.'));
   const documents=(data.documents||[]).filter(d=>d.purpose==='PATIENT_PROVIDED');
   if(!documents.length&&data.can_view_patient)list.appendChild(node('p','Sin documentos aportados en esta cita.'));
   for(const d of documents){const row=node('article');row.className='patient-document-item';row.dataset.patientDocumentId=d.id;
    row.append(node('strong',d.label),node('small',({REPORT:'Informe / resultado',PRESCRIPTION:'Receta aportada',ADMINISTRATIVE:'Otro documento'}[d.document_kind]||d.document_kind)+' · Cita #'+d.appointment_id+' · '+d.appointment_date),node('small',(d.created_at?new Date(d.created_at).toLocaleString('es-PE',{timeZone:'America/Lima'}):'Sin fecha')+' · '+(d.actor||'Responsable histórico')));
    const download=node('a','Descargar');download.href=d.download_url;download.className='admission-btn agenda-btn';row.appendChild(download);
    if(d.preview_url){const preview=node('details'),summary=node('summary','Visualizar archivo');preview.appendChild(summary);let generation=0,loadingTask=null,pdf=null,renderTask=null,controller=null;
     const dispose=()=>{generation++;controller?.abort();renderTask?.cancel();loadingTask?.destroy()?.catch(()=>{});pdf=null;loadingTask=null;renderTask=null;};previewDisposers.add(dispose);
     preview.addEventListener('toggle',async()=>{
      if(!preview.open){dispose();while(preview.children.length>1)preview.lastChild.remove();return;}
      const current=++generation;
      if(d.mime!=='application/pdf'){const img=node('img');img.src=d.preview_url;img.alt=d.label;img.className='patient-document-preview';preview.appendChild(img);return;}
      const message=node('p','Preparando vista privada…');message.setAttribute('role','status');preview.appendChild(message);
      try{
       const assets=doc.querySelector('script[data-patient-document-module]')?.dataset;if(!assets)throw new Error('Missing renderer');
       if(!pdfModule)pdfModule=import(assets.patientDocumentModule).catch(error=>{pdfModule=null;throw error;});
       const lib=await pdfModule;lib.GlobalWorkerOptions.workerSrc=assets.patientDocumentWorker;
       controller=new AbortController();const response=await fetch(d.preview_url,{credentials:'same-origin',signal:controller.signal,headers:{Accept:'application/pdf'}});
       if(!response.ok||!(response.headers.get('Content-Type')||'').includes('application/pdf'))throw new Error('Not available');
       const bytes=new Uint8Array(await response.arrayBuffer());if(bytes.length>8192*1024)throw new Error('Size');if(current!==generation)return;
       loadingTask=lib.getDocument(pdfOptions(bytes));pdf=await loadingTask.promise;if(current!==generation)return;
       const controls=node('div'),prev=node('button','Página anterior'),next=node('button','Página siguiente'),counter=node('span'),canvas=node('canvas');
       controls.className='patient-pdf-controls';prev.type=next.type='button';prev.className=next.className='admission-btn agenda-btn';canvas.className='patient-pdf-canvas';canvas.setAttribute('role','img');
       controls.append(prev,counter,next);preview.append(controls,canvas);let pageNumber=1;
       async function draw(number){if(!pdf||current!==generation)return;prev.disabled=next.disabled=true;message.textContent='Dibujando página…';
        const page=await pdf.getPage(number),original=page.getViewport({scale:1});
        const scale=Math.min(1.5,2048/original.width,2048/original.height,Math.max(240,area.clientWidth-24)/original.width),viewport=page.getViewport({scale});
        canvas.width=Math.ceil(viewport.width);canvas.height=Math.ceil(viewport.height);canvas.setAttribute('aria-label','Página '+number+' de '+pdf.numPages+' · '+d.label);
        renderTask=page.render({canvasContext:canvas.getContext('2d'),viewport});await renderTask.promise;
        if(current!==generation)return;canvas.dataset.rendered='true';pageNumber=number;counter.textContent='Página '+number+' de '+pdf.numPages;prev.disabled=number===1;next.disabled=number===pdf.numPages;message.textContent='';page.cleanup();
       }
       const change=number=>draw(number).catch(()=>{if(current===generation)message.textContent='No se pudo mostrar la página. Puedes descargar el archivo privado.';});
       prev.addEventListener('click',()=>change(pageNumber-1));next.addEventListener('click',()=>change(pageNumber+1));await draw(1);
      }catch(error){if(current===generation)message.textContent='Vista previa no disponible. Puedes descargar el archivo privado para revisarlo.';}
     });row.appendChild(preview);
    }
    if(d.can_replace){const replace=node('button','Reemplazar archivo');replace.type='button';replace.className='admission-btn agenda-btn';replace.addEventListener('click',()=>{if(busy)return;replacement=d;label.value=d.label;kind.value=d.document_kind;file.value='';reason.hidden=false;reason.required=true;submit.textContent='Confirmar reemplazo';key=null;openForm();dirty();file.focus();});row.appendChild(replace);}
    if(d.replaces_document_id)row.appendChild(node('small','Reemplaza al documento #'+d.replaces_document_id+'; archivo anterior conservado en auditoría.'));
    list.appendChild(row);
   }if(data.document_history?.length){const history=node('details'),summary=node('summary','Historial documental · versiones anteriores');history.appendChild(summary);for(const item of data.document_history){history.appendChild(node('p','#'+item.id+' · '+item.label+' · '+(item.actor||'Responsable histórico')+' · '+new Date(item.created_at).toLocaleString('es-PE',{timeZone:'America/Lima'})+' · Archivo anterior conservado.'));}list.appendChild(history);}
   const clinical=(data.documents||[]).filter(d=>d.purpose==='REEVALUATION_ORDER');if(clinical.length){const group=node('details');group.appendChild(node('summary','Documentos clínicos acreditados'));for(const d of clinical){const link=node('a',d.label);link.href=d.download_url;link.className='admission-btn agenda-btn';group.appendChild(link);}list.appendChild(group);}loaded=true;
  }catch(e){status.textContent=e.message;}finally{loading=null;}})();return loading;
 }
 form.addEventListener('submit',async e=>{e.preventDefault();e.stopPropagation();if(busy)return;const name=label.value.replace(/^[\s\u200B\uFEFF]+|[\s\u200B\uFEFF]+$/g,'');if(!name){status.textContent='Escribe un nombre para identificar el documento.';label.focus();return;}if(!file.files.length){status.textContent='Selecciona un archivo.';file.focus();return;}if(file.files[0].size>8192*1024){status.textContent='El archivo supera 8 MB.';return;}
  key=key||crypto.randomUUID();const body=new FormData();body.set('request_key',key);body.set('label',name);body.set('document_kind',kind.value);body.set('file',file.files[0]);
  if(replacement){body.set('expected_version',replacement.version);body.set('reason',reason.value.trim());}else body.set('purpose','PATIENT_PROVIDED');
  busy=true;onBusy(true);submit.disabled=true;cancel.disabled=true;status.textContent='Guardando documento privado…';
  try{await readResponse(await fetch(replacement?url+'/'+replacement.id+'/replace':url,{method:'POST',credentials:'same-origin',headers:headers(),body}));reset();await load(true);status.textContent='Documento guardado; historial conservado.';await onUploaded();}
  catch(error){status.textContent=error.message;}finally{busy=false;onBusy(false);submit.disabled=false;cancel.disabled=false;}
 });
 return {element:area,load};
}
return {create,pdfOptions};}));
