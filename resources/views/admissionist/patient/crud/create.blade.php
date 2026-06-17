<div class="modal fade" id="patientModalCreate" tabindex="-1" aria-labelledby="patientModalCreateLabel" aria-hidden="true">

    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header">
                <h5 class="modal-title" id="patientModalCreateLabel">
                    Registro de Paciente
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>

            <form id="formCreatePatient" method="POST" action="{{ route('admissionit.patient.store') }}">

                @csrf

                <div class="modal-body">

                    <!-- DATOS PERSONALES -->
                    <h6 class="fw-bold mb-3">
                        Datos Personales
                    </h6>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" name="nombre_paciente" id="nombre_paciente"
                                placeholder="Nombre completo">

                            <span class="text-danger error-text nombre_paciente_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Apellido paterno</label>
                            <input type="text" class="form-control" name="apellido_paterno" id="apellido_paterno"
                                placeholder="Apellido paterno">

                            <span class="text-danger error-text apellido_paterno_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Apellido materno</label>
                            <input type="text" class="form-control" name="apellido_materno" id="apellido_materno"
                                placeholder="Apellido materno">

                            <span class="text-danger error-text apellido_materno_error"></span>
                        </div>
                    </div>

                    <!-- IDENTIFICACION -->
                    <hr>

                    <h6 class="fw-bold mb-3">
                        Identificación
                    </h6>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Tipo documento</label>
                            <select class="form-control" name="tipo_identificacion" id="tipo_identificacion">
                                <option value="DNI">DNI</option>
                                <option value="CARNET EXTRANJERIA">CARNET EXTRANJERIA</option>
                                <option value="PTP">PTP</option>
                                <option value="TAM">TAM</option>
                                <option value="RUC">RUC</option>
                                <option value="PASAPORTE">PASAPORTE</option>
                                <option value="SALVOCONDUCTO">SALVOCONDUCTO</option>
                                <option value="SIN DOCUMENTOS">SIN DOCUMENTOS</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Número documento</label>
                            <input type="text" class="form-control" name="numero_identidad" id="numero_identidad"
                                placeholder="Ej. 12345678">
                            <span class="text-danger error-text numero_identidad_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Género</label>
                            <select class="form-control" name="genero_paciente" id="genero_paciente">
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
                            <label class="form-label">Fecha de nacimiento</label>
                            <input type="date" class="form-control" name="fecha_nacimiento" id="fecha_nacimiento">
                            <span class="text-danger error-text fecha_nacimiento_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Estado civil</label>
                            <select class="form-control" name="estado_civil" id="estado_civil">
                                <option value="CASADO">CASADO</option>
                                <option value="SOLTERO">SOLTERO</option>
                                <option value="VIUDO">VIUDO</option>
                                <option value="DIVORCIADO">DIVORCIADO</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Ocupación</label>
                            <input type="text" class="form-control" name="ocupacion" id="ocupacion">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Grado de instrucción</label>
                            <input type="text" class="form-control" name="grado_instruccion"
                                id="grado_instruccion" placeholder="Primaria, Secundaria, Superior">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Correo electrónico</label>
                            <input type="email" class="form-control" name="email" id="email"
                                placeholder="correo@ejemplo.com">
                            <span class="text-danger error-text email_error"></span>
                        </div>

                    </div>

                    <!-- CONTACTO -->
                    <hr>

                    <h6 class="fw-bold mb-3">Información de Contacto</h6>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Teléfono</label>
                            <input type="text" class="form-control" name="telefono" id="telefono"
                                placeholder="999888777">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Canal</label>
                            <select class="form-control" name="channel_id" id="channel_id">
                                @foreach ($channels as $channel)
                                    <option value="{{ $channel->id }}">
                                        {{ $channel->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Medio Interacción</label>
                            <select class="form-control" name="interaction_medium_id" id="interaction_medium_id">
                                @foreach ($interaction_media as $medium)
                                    <option value="{{ $medium->id }}">
                                        {{ $medium->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Dirección</label>
                            <input type="text" class="form-control" name="direccion" id="direccion"
                                placeholder="Av. Principal 123">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label"> Familiar de contacto</label>
                            <input type="text" class="form-control" name="familiar_contacto"
                                id="familiar_contacto" placeholder="Nombre y relación">
                        </div>

                    </div>

                    <!-- RESPONSABLE -->
                    <hr>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="responsable_id" name="responsable_id"
                            value="responsable">
                        <label class="form-check-label" for="responsable_id">
                            Registrar acompañante o responsable
                        </label>
                    </div>

                    <div class="oculto_card_responsable" id="modal_responsable">
                        <h6 class="fw-bold mb-3">Datos del Responsable</h6>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Parentesco</label>
                                <select class="form-control" name="responsable_tipo" id="responsable_tipo">
                                    <option value="PAPA">PAPÁ</option>
                                    <option value="MAMA">MAMÁ</option>
                                    <option value="HERMANO">HERMANO</option>
                                    <option value="PRIMO">PRIMO</option>
                                    <option value="AMIGO">AMIGO</option>
                                    <option value="CONOCIDO CERCANO">CONOCIDO CERCANO</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Nombre completo</label>
                                <input type="text" class="form-control" name="nombre_responsable"
                                    id="nombre_responsable" placeholder="Nombre del responsable">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Teléfono</label>
                                <input type="text" class="form-control" name="telefono_responsable"
                                    id="telefono_responsable" placeholder="999777666">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tipo documento</label>
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

                            <div class="col-md-6">
                                <label class="form-label">Número documento</label>
                                <input type="text" class="form-control" name="numero_identidad_responsable"
                                    id="numero_identidad_responsable" placeholder="12345678">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button type="submit" class="btn btn-primary btn-save">
                        Guardar Paciente
                    </button>
                </div>
            </form>

        </div>
    </div>

</div>
