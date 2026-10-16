<div class="modal fade catalog-modal" id="doctorServiceModalEdit" tabindex="-1" aria-labelledby="doctorServiceEditTitle" aria-hidden="true">
 <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header bg-dark"><h5 class="modal-title text-white" id="doctorServiceEditTitle">Editar precios del profesional</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
  <form id="formUpdateDoctorService" method="POST" action="{{ route('master.service.doctor.update') }}">
   @csrf @method('PUT')
   <input type="hidden" name="doctor_service_id"><input type="hidden" name="catalog_version"><input type="hidden" name="doctor_id"><input type="hidden" name="service_id">
   <div class="modal-body"><p data-assignment-context></p><p data-price-source class="alert alert-info"></p>
    <div class="alert alert-danger" data-catalog-errors role="alert" tabindex="-1" hidden></div>
    <div class="row g-3">@include('admin.master.doctor-service.crud.specialty-authorization')
     <div class="col-md-6"><label class="form-label">Primera consulta S/</label><input class="form-control" name="precio_estandar" type="number" min="0.01" step="0.01"><small>Vacío: usar precio institucional por defecto, si existe.</small></div>
     <div class="col-md-6"><label class="form-label">Reevaluación S/</label><input class="form-control" name="reconsulta" type="number" min="0.01" step="0.01"><small>Solo con indicación válida. Mismo día aplica su regla clínica; días 1–15 este importe.</small></div>
     <div class="col-md-6"><label class="form-label">Plazo registrado (heredado)</label><input class="form-control" name="dias" type="number" readonly><small>Se conserva este valor. El motor clínico vigente aplica el límite aprobado de 15 días.</small></div>
     <div class="col-md-6"><label class="form-label">Estado de asignación</label><select name="estado" class="form-control"><option value="ACTIVO">Activo</option><option value="INACTIVO">Inactivo</option></select></div>
    </div>
   </div>
   <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar precios</button></div>
  </form>
 </div></div>
</div>
