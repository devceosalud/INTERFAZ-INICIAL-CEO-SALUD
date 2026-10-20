(function(root,factory){const api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else root.PatientRecordForm=api;})(typeof window!=='undefined'?window:globalThis,function(){
'use strict';
const FIELDS={tipo_identificacion:'document-type',numero_identidad:'document-number',nombre:'name',apellido_paterno:'paternal-name',apellido_materno:'maternal-name',telefono_secundario:'phone-secondary',email:'email',fecha_nacimiento:'birth-date',genero:'gender',estado_civil:'civil-status',direccion:'address',channel_id:'channel',interaction_medium_id:'interaction-medium',ocupacion:'occupation',grado_instruccion:'education',familiar_contacto:'family-contact'};
const RESPONSIBLE={responsable_parentesco:'relationship',responsable_nombres:'name',responsable_telefono:'phone',responsable_tipo_identificacion:'document-type',responsable_numero_identidad:'document-number'};
const control=(form,id)=>form.querySelector('#patient-'+id);
function payload(form,{phoneUnparsed=false,expectedRevision=null,appointmentId=null}={}){
 const data={};for(const[key,id]of Object.entries(FIELDS)){const value=control(form,id).value.trim();data[key]=value===''?null:value;}
 data.telefono_prefijo=control(form,'phone-prefix').value||'+51';data.telefono_numero=control(form,'phone').value.trim();data.telefono_sin_separar=phoneUnparsed;data.registrar_responsable=control(form,'register-responsible').checked;
 if(data.registrar_responsable)for(const[key,id]of Object.entries(RESPONSIBLE))data[key]=control(form,'responsible-'+id).value.trim();
 if(expectedRevision)data.expected_revision=expectedRevision;if(appointmentId)data.appointment_id=appointmentId;return data;
}
function hydrate(form,patient,phoneApi){
 const ensure=(el,v)=>{if(el.tagName==='SELECT'&&v!==''&&![...el.options].some(o=>o.value===v)){const opt=form.ownerDocument.createElement('option');opt.value=v;opt.textContent=v;el.appendChild(opt);}el.value=v;};
 for(const[key,id]of Object.entries(FIELDS))ensure(control(form,id),String(patient[key]??''));
 const phone=phoneApi.split(patient.telefono);ensure(control(form,'phone-prefix'),phone.prefijo||'+51');control(form,'phone').value=phone.parsed?phone.numero:phone.raw;
 const r=patient.responsable;control(form,'register-responsible').checked=!!r;control(form,'responsible').hidden=!r;
 const source={responsable_parentesco:r?.parentezco||'PAPA',responsable_nombres:r?.nombres||'',responsable_telefono:r?.telefono||'',responsable_tipo_identificacion:r?.tipo_identificacion||'DNI',responsable_numero_identidad:r?.numero_identidad||''};
 for(const[key,id]of Object.entries(RESPONSIBLE))ensure(control(form,'responsible-'+id),source[key]);return {phoneUnparsed:!phone.parsed};
}
return{FIELDS,RESPONSIBLE,payload,hydrate};});
