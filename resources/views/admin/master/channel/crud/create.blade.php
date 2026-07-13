<div class="modal fade" id="channelModalCreate" tabindex="-1" aria-labelledby="channelModalCreateLabel" aria-hidden="true">

    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header bg-dark">
                <h5 class="modal-title text-white" id="channelModalCreateLabel">
                    Registro del Canal
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>

            <form id="formCreateChannel" method="POST" action="{{ route('master.channel.store') }}">

                @csrf

                <div class="modal-body">

                    <!-- DATOS PERSONALES -->
                    <h6 class="fw-bold mb-3">
                        Datos Del Canal
                    </h6>

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label text-primary">Nombre del Canal <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre_canal" id="nombre_canal"
                                placeholder="Nombre del Canal">

                            <span class="text-danger error-text nombre_canal_error"></span>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="modal-footer bg-dark">
                    <button type="button" class="btn btn-dark btn-rounded" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <input type="submit" class="btn btn-primary btn-save btn-rounded" value="Guardar Canal">
                </div>
            </form>

        </div>
    </div>

</div>
