<div class="modal fade" id="serviceModalEdit" tabindex="-1" aria-labelledby="serviceModalEditLabel" aria-hidden="true">

    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header bg-dark">
                <h5 class="modal-title text-white" id="serviceModalEditLabel">
                    Actualizar
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>

            <form id="formUpdateService" method="POST" action="{{ route('master.service.update') }}">

                @method('put')
                @csrf

                <div class="modal-body">

                    <!-- DATOS PERSONALES -->
                    <h6 class="fw-bold mb-3">
                        Datos del Servicio
                    </h6>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <input type="hidden" name="service_id_edit" id="service_id_edit">
                            <label class="form-label text-primary">Nombre<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre_edit" id="nombre_edit"
                                placeholder="Nombre completo">

                            <span class="text-danger error-text nombre_edit_error"></span>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-primary">Especialidades<span
                                    class="text-danger">*</span></label>
                            <select class="form-control" name="specialty_id_edit" id="specialty_id_edit">
                                @foreach ($specialties as $specialty)
                                    <option value="{{ $specialty->id }}">{{ $specialty->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="modal-footer bg-dark">
                    <button type="button" class="btn btn-dark btn-rounded" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <input type="submit" class="btn btn-primary btn-save btn-rounded" value="Actualizar Servicio">
                </div>
            </form>

        </div>
    </div>

</div>
