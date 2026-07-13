<div class="modal fade" id="patientModalEdit" tabindex="-1" aria-labelledby="patientModalEditLabel" aria-hidden="true">

    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header bg-dark">
                <h5 class="modal-title text-white" id="patientModalEditLabel">
                    Actualizar Paciente
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>

            <form id="formUpdatePatient" method="POST" action="{{ route('admissionit.patient.update') }}">

                @csrf
                @method('PUT')

                <input type="hidden" name="id" id="patient_id_edit">

                <div class="modal-body">

                    <!-- DATOS PERSONALES -->
                    <h6 class="fw-bold mb-3">
                        Datos Personales
                    </h6>

                    <div class="row g-3">

                        <div class="col-md-4">
                            <label class="form-label text-primary">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre_paciente_edit"
                                id="nombre_paciente_edit" placeholder="Nombre completo">

                            <span class="text-danger error-text nombre_paciente_edit_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Apellido paterno <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="apellido_paterno_edit"
                                id="apellido_paterno_edit" placeholder="Apellido paterno">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Apellido materno <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="apellido_materno_edit"
                                id="apellido_materno_edit" placeholder="Apellido materno">
                        </div>

                    </div>

                    <!-- IDENTIFICACION -->
                    <hr>

                    <h6 class="fw-bold mb-3">
                        Identificación
                    </h6>

                    <div class="row g-3">

                        <div class="col-md-4">
                            <label class="form-label text-primary">Tipo documento <span class="text-danger">*</span></label>
                            <select class="form-control" name="tipo_identificacion_edit" id="tipo_identificacion_edit">
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
                            <label class="form-label text-primary">Número documento <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="numero_identidad_edit"
                                id="numero_identidad_edit" placeholder="12345678">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Género <span class="text-danger">*</span></label>
                            <select class="form-control" name="genero_paciente_edit" id="genero_paciente_edit">
                                <option value="HOMBRE">Hombre</option>
                                <option value="MUJER">Mujer</option>
                            </select>
                        </div>

                    </div>

                    <!-- INFORMACION GENERAL -->
                    <hr>

                    <h6 class="fw-bold mb-3">
                        Información General
                    </h6>

                    <div class="row g-3">

                        <div class="col-md-4">
                            <label class="form-label text-primary">Fecha de nacimiento <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="fecha_nacimiento_edit"
                                id="fecha_nacimiento_edit">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Estado civil</label>
                            <select class="form-control" name="estado_civil_edit" id="estado_civil_edit">
                                <option value="CASADO">CASADO</option>
                                <option value="SOLTERO">SOLTERO</option>
                                <option value="VIUDO">VIUDO</option>
                                <option value="DIVORCIADO">DIVORCIADO</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Ocupación</label>
                            <input type="text" class="form-control" name="ocupacion_edit" id="ocupacion_edit">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-primary">Grado de instrucción</label>
                            <input type="text" class="form-control" name="grado_instruccion_edit"
                                id="grado_instruccion_edit" placeholder="Primaria, Secundaria, Superior">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-primary">Correo electrónico <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email_edit" id="email_edit"
                                placeholder="correo@ejemplo.com">
                        </div>

                    </div>

                    <!-- CONTACTO -->
                    <hr>

                    <h6 class="fw-bold mb-3">
                        Información de Contacto
                    </h6>

                    <div class="row g-3">

                        <div class="col-md-4">
                            <label class="form-label text-primary">Teléfono</label>
                            <input type="text" class="form-control" name="telefono_edit" id="telefono_edit"
                                placeholder="999888777">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Canal</label>
                            <select class="form-control" name="channel_edit" id="channel_edit">
                                <option value="">Seleccione</option>

                                @foreach ($channels as $channel)
                                    <option value="{{ $channel->id }}">
                                        {{ $channel->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Medio Interacción</label>
                            <select class="form-control" name="interaction_medium_edit" id="interaction_medium_edit">
                                <option value="">Seleccione</option>

                                @foreach ($interaction_media as $medium)
                                    <option value="{{ $medium->id }}">
                                        {{ $medium->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-primary">Dirección</label>
                            <input type="text" class="form-control" name="direccion_edit" id="direccion_edit"
                                placeholder="Av. Principal 123">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-primary">Familiar de contacto</label>
                            <input type="text" class="form-control" name="familiar_contacto_edit"
                                id="familiar_contacto_edit" placeholder="Nombre y relación">
                        </div>

                    </div>

                </div>

                <!-- FOOTER -->
                <div class="modal-footer bg-dark">

                    <button type="button" class="btn btn-dark btn-rounded" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <input type="submit" class="btn btn-primary btn-rounded" value="Actualizar Paciente">

                </div>

            </form>

        </div>
    </div>

</div>
