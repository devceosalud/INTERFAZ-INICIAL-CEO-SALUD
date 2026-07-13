<div class="modal fade" id="responsibleModalEdit" tabindex="-1" aria-labelledby="responsibleModalEditLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header bg-dark">
                <h5 class="modal-title text-white" id="responsibleModalEditLabel">
                    Editar Responsable
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>

            <form id="formUpdateResponsible" method="POST" action="{{ route('admissionit.responsible.update') }}">

                @method('put')
                @csrf

                <div class="modal-body">

                    <!-- DATOS PERSONALES -->
                    <h6 class="fw-bold mb-3">
                        Datos Personales
                    </h6>

                    <div class="row g-3">
                        <input type="hidden" name="responsible_id_edit" id="responsible_id_edit">
                        <div class="col-md-4">
                            <input type="hidden">
                            <label class="form-label text-primary">Parentesco</label>
                            <select class="form-control" name="responsable_tipo" id="responsable_tipo">
                                <option value="PAPA">PAPÁ</option>
                                <option value="MAMA">MAMÁ</option>
                                <option value="HERMANO">HERMANO</option>
                                <option value="PRIMO">PRIMO</option>
                                <option value="AMIGO">AMIGO</option>
                                <option value="CONOCIDO CERCANO">CONOCIDO CERCANO</option>
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label text-primary">Nombre completo</label>
                            <input type="text" class="form-control" name="nombre_responsable" id="nombre_responsable"
                                placeholder="Nombre del responsable">

                            <span class="text-danger error-text nombre_responsable_error"></span>
                        </div>
                    </div>

                    <!-- IDENTIFICACION -->
                    <hr>

                    <h6 class="fw-bold mb-3">
                        Identificación
                    </h6>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label text-primary">Tipo documento</label>
                            <select class="form-control" name="tipo_identificacion_responsable"
                                id="tipo_identificacion_responsable">
                                <option value="DNI">DNI</option>
                                <option value="CARNET EXTRANJERIA">CARNET EXTRANJERIA</option>
                                <option value="PTP">PTP</option>
                                <option value="TAM">TAM</option>
                                <option value="RUC">RUC</option>
                                <option value="PASAPORTE">PASAPORTE</option>
                                <option value="SIN DOCUMENTOS">SIN DOCUMENTOS</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Número documento</label>
                            <input type="text" class="form-control" name="numero_identidad_responsable"
                                id="numero_identidad_responsable" placeholder="12345678">

                            <span class="text-danger error-text numero_identidad_responsable_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Teléfono</label>
                            <input type="text" class="form-control" name="telefono_responsable"
                                id="telefono_responsable" placeholder="999777666">

                            <span class="text-danger error-text telefono_responsable_error"></span>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="modal-footer bg-dark">
                    <button type="button" class="btn btn-dark btn-rounded" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <input type="submit" class="btn btn-primary btn-save btn-rounded" value="Actualizar Responsable">
                </div>
            </form>

        </div>
    </div>

</div>
