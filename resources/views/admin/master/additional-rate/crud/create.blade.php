<div class="modal fade" id="additonalRateModalCreate" tabindex="-1" aria-labelledby="additonalRateModalCreateLabel" aria-hidden="true">

    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header bg-dark">
                <h5 class="modal-title text-white" id="additonalRateModalCreateLabel">
                    Registro de Tarifa
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>

            <form id="formCreateAdditionalRate" method="POST" action="{{ route('master.additionalRate.store') }}">

                @csrf

                <div class="modal-body">

                    <!-- DATOS PERSONALES -->
                    <h6 class="fw-bold mb-3">
                        Datos de la Tarifa
                    </h6>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label text-primary">Nombre de la Tarifa<span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre_tarifa" id="nombre_tarifa"
                                placeholder="Nombre completo">

                            <span class="text-danger error-text nombre_tarifa_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Tipo Tarifa <span
                                    class="text-danger">*</span></label>
                            <select class="form-control" name="tipo_tarifa" id="tipo_tarifa">
                                <option value="MONTO_FIJO">MONTO FIJO</option>
                                <option value="PORCENTAJE">PORCENTAJE</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Saldo<span
                                    class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="saldo_tarifa" id="saldo_tarifa">

                            <span class="text-danger error-text saldo_tarifa_error"></span>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="modal-footer bg-dark">
                    <button type="button" class="btn btn-dark btn-rounded" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <input type="submit" class="btn btn-primary btn-save btn-rounded" value="Guardar Tarifa">
                </div>
            </form>

        </div>
    </div>

</div>
