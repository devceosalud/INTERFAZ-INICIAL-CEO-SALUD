window.addEventListener('DOMContentLoaded',()=>{
 const editor=window.CatalogEditor,update=document.getElementById('formUpdateAdditionalRate');
 [document.getElementById('formCreateAdditionalRate'),update].forEach(editor.bind);
 document.addEventListener('click',async e=>{
  const edit=e.target.closest('.edit-additional-rate'),remove=e.target.closest('.delete-additional-rate');
  if(edit){e.preventDefault();try{const data=await editor.request('/api/admin/additonal-rate/search',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:Number(edit.dataset.id)})});const p=data.additionalRate;
   editor.fill(update,{additional_rate_id_edit:p.id,nombre_edit_tarifa:p.nombre,tipo_tarifa:p.tipo_tarifa,saldo_tarifa:p.tarifa,fecha_inicio:p.fecha_inicio?.slice(0,10),fecha_fin:p.fecha_fin?.slice(0,10),estado:p.estado,catalog_version:p.catalog_version});editor.errors(update,null);editor.show(document.getElementById('additonalRateModalEdit'));
  }catch(error){window.Swal.fire({icon:'error',text:error.message});}}
  if(remove){e.preventDefault();await editor.deactivate('/master/admin/additional-rate/delete',Number(remove.dataset.id),Number(remove.dataset.version));}
 });
});
