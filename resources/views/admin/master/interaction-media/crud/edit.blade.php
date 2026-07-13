<div class="modal fade" id="interactionMediaModalEdit" tabindex="-1" aria-labelledby="interactionMediaModalEditLabel" aria-hidden="true">

    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header bg-dark">
                <h5 class="modal-title text-white" id="interactionMediaModalEditLabel">
                    Actualizar Medio de Comunicación
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>

            <form id="formUpdateInteractionMedia" method="POST" action="{{ route('master.interactionMedia.update') }}">

                @method('put')
                @csrf

                <div class="modal-body">

                    <!-- DATOS PERSONALES -->
                    <h6 class="fw-bold mb-3">
                        Datos Del Medio
                    </h6>

                    <div class="row g-3">
                        <div class="col-md-12">
                            <input type="hidden" name="interaction_media_id_edit" id="interaction_media_id_edit">
                            <label class="form-label text-primary">Nombre del Medio <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre_edit_interaccion_medio" id="nombre_edit_interaccion_medio"
                                placeholder="Nombre del Medio">

                            <span class="text-danger error-text nombre_edit_interaccion_medio_error"></span>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="modal-footer bg-dark">
                    <button type="button" class="btn btn-dark btn-rounded" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <input type="submit" class="btn btn-primary btn-save btn-rounded" value="Actualizar Medio">
                </div>
            </form>

        </div>
    </div>

</div>
