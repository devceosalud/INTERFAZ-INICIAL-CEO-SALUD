<div class="modal fade" id="additonalRateModalEdit" tabindex="-1" aria-labelledby="additonalRateModalEditLabel" aria-hidden="true">

    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header bg-dark">
                <h5 class="modal-title text-white" id="additonalRateModalEditLabel">
                    Actualizar Tarifa
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>

            <form id="formUpdateAdditionalRate" method="POST" action="{{ route('master.additionalRate.update') }}">

                @method('put')
                @csrf

                <div class="modal-body">

                    <!-- DATOS PERSONALES -->
                    <h6 class="fw-bold mb-3">
                        Datos de la Tarifa
                    </h6>

                    <div class="row g-3">
                        <div class="col-md-4">
                             <input type="hidden" name="additional_rate_id_edit" id="additional_rate_id_edit">
                            <label class="form-label text-primary">Nombre de la Tarifa<span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre_edit_tarifa" id="nombre_edit_tarifa"
                                placeholder="Nombre de la tarifa">

                            <span class="text-danger error-text nombre_edit_tarifa_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Tipo Tarifa <span
                                    class="text-danger">*</span></label>
                            <select class="form-control" name="tipo_edit_tarifa" id="tipo_edit_tarifa">
                                <option value="MONTO_FIJO">MONTO FIJO</option>
                                <option value="PORCENTAJE">PORCENTAJE</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Saldo<span
                                    class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="saldo_edit_tarifa" id="saldo_edit_tarifa">

                            <span class="text-danger error-text saldo_edit_tarifa_error"></span>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="modal-footer bg-dark">
                    <button type="button" class="btn btn-dark btn-rounded" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <input type="submit" class="btn btn-primary btn-save btn-rounded" value="Actualizar Tarifa">
                </div>
            </form>

        </div>
    </div>

</div>
