window.addEventListener('DOMContentLoaded',()=>{
 const editor=window.CatalogEditor;
 ['formCreateService','formUpdateService'].forEach(id=>editor.bind(document.getElementById(id)));
 document.addEventListener('click',async e=>{
  const edit=e.target.closest('.edit-service');const remove=e.target.closest('.delete-service');
  if(edit){e.preventDefault();const modal=document.getElementById('serviceModalEdit'),form=modal.querySelector('form');try{
   const data=await editor.request('/api/admin/service/search',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:edit.dataset.id})});const p=data.service;
   editor.fill(form,{...p,service_id_edit:p.id,nombre_edit:p.nombre,specialty_id_edit:p.specialty_id,shared_specialty_ids:(p.shared_specialties||[]).filter(s=>s.pivot.estado==='ACTIVO').map(s=>s.id),specialty_authorization_reason:''});editor.errors(form,null);editor.show(modal);
  }catch(error){window.Swal.fire({icon:'error',text:error.message});}}
  if(remove){e.preventDefault();await editor.deactivate('/master/admin/service/delete',Number(remove.dataset.id),Number(remove.dataset.version));}
 });
});
