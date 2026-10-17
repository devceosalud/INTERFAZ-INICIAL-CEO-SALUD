window.addEventListener('DOMContentLoaded',()=>{
 const editor=window.CatalogEditor;
 const create=document.getElementById('formCreateDoctorService'),update=document.getElementById('formUpdateDoctorService');[create,update].forEach(editor.bind);
 function policy(form,service){const institutional=service?.pricing_policy==='INSTITUTIONAL';for(const name of ['precio_estandar','reconsulta']){const f=form.elements.namedItem(name);if(f){f.disabled=institutional;if(institutional)f.value='';}}
  const note=form.querySelector('[data-price-source]');if(note)note.textContent=institutional?'Precio institucional: S/ '+(service.institutional_price??'sin configurar')+'. La asignación autoriza a este profesional.':'Primera consulta y reevaluación del profesional. La reevaluación exige indicación y respeta los límites clínicos.';}
 function compatibility(form){
  const approved=form.elements.namedItem('specialty_exception_authorized'),reason=form.elements.namedItem('specialty_exception_reason');if(reason)reason.required=Boolean(approved?.checked);
  if(form===create){const doctor=form.elements.namedItem('doctor_id')?.selectedOptions[0],select=form.elements.namedItem('service_id');if(select){for(const option of select.options){if(!option.value)continue;const allowed=JSON.parse(option.dataset.specialties||'[]').map(String).includes(String(doctor?.dataset.specialty));option.disabled=!allowed&&!approved?.checked;}if(select.selectedOptions[0]?.disabled)select.value='';
   // Bootstrap Select caches disabled options; synchronize only after compatibility and selection are final.
   if(window.jQuery?.fn?.selectpicker)window.jQuery(select).selectpicker('refresh');}}
  const note=form.querySelector('[data-specialty-message]');if(note){const allowed=form===create?Boolean(form.elements.namedItem('service_id')?.value):form.dataset.specialtyCompatible==='1';note.hidden=allowed;if(!allowed&&form===update)form.querySelector('[data-specialty-authorization]').open=true;note.textContent='Revisa la compatibilidad de especialidad. Un servicio incompatible requiere autorización explícita y motivo; se valida también en servidor.';}
 }
 [create,update].filter(Boolean).forEach(form=>form.elements.namedItem('specialty_exception_authorized')?.addEventListener('change',()=>compatibility(form)));create?.elements.namedItem('doctor_id')?.addEventListener('change',()=>compatibility(create));
 create?.elements.namedItem('service_id')?.addEventListener('change',e=>{compatibility(create);const option=e.target.selectedOptions[0];policy(create,{pricing_policy:option.dataset.policy,institutional_price:option.dataset.price});});
 for(const [modalId,form] of [['doctorServiceModalCreate',create],['doctorServiceModalEdit',update]]){
  if(form)document.getElementById(modalId)?.addEventListener('shown.bs.modal',()=>compatibility(form));
 }
 if(create)create.elements.namedItem('service_id')?.dispatchEvent(new Event('change'));
 if(update)compatibility(update);
 document.addEventListener('click',async e=>{const edit=e.target.closest('.edit-doctor-service'),remove=e.target.closest('.delete-doctor-service');
  if(edit){e.preventDefault();try{const data=await editor.request('/master/admin/doctor/services/'+encodeURIComponent(edit.dataset.id));const p=data.assignment;
   editor.fill(update,{doctor_service_id:p.id,doctor_id:p.doctor_id,service_id:p.service_id,precio_estandar:p.precio_primera_consulta,reconsulta:p.precio_reconsulta,dias:p.dias_reconsulta,catalog_version:p.catalog_version,estado:p.estado,specialty_exception_authorized:Boolean(p.specialty_exception_approved_by_user_id),specialty_exception_reason:p.specialty_exception_reason});
   update.dataset.specialtyCompatible=String(p.doctor.specialty_id)===String(p.service.specialty_id)||(p.service.shared_specialties||[]).some(s=>s.pivot.estado==='ACTIVO'&&String(s.id)===String(p.doctor.specialty_id))?'1':'0';compatibility(update);
   update.querySelector('[data-assignment-context]').textContent=(p.doctor?.nombre||'Profesional #'+p.doctor_id)+' · '+p.service.nombre;policy(update,p.service);editor.errors(update,null);editor.show(document.getElementById('doctorServiceModalEdit'));
  }catch(error){window.Swal.fire({icon:'error',text:error.message});}}
  if(remove){e.preventDefault();await editor.deactivate('/master/admin/doctor/services/delete',Number(remove.dataset.id),Number(remove.dataset.version));}
 });
});