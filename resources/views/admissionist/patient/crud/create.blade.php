<div class="modal fade" id="patientModalCreate" data-bs-backdrop="static" tabindex="-1"
    aria-labelledby="patientModalCreateLabel" aria-hidden="true">

    <div class="modal-dialog modal-xl">

        <div class="modal-content">
            {{-- ========================================================= --}}
            {{-- HEADER --}}
            {{-- ========================================================= --}}
            <div class="modal-header bg-primary text-white">
                <div>
                    <h5 class="modal-title mb-1" id="patientModalCreateLabel">
                        <i class="fas fa-user-plus me-2"></i>
                        Registro de paciente
                    </h5>
                    <small> Complete los datos del paciente </small>
                </div>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>


            <form id="formCreatePatient" method="POST" action="{{ route('admissionit.patient.store') }}">
                @csrf

                {{-- ===================================================== --}}
                {{-- BODY --}}
                {{-- ===================================================== --}}

                <div class="modal-body p-4">
                    {{-- ================================================= --}}
                    {{-- 1. IDENTIFICACIÓN --}}
                    {{-- ================================================= --}}

                    <div class="mb-4">
                        <div class="d-flex align-items-center border-bottom pb-2 mb-3">
                            <i class="fas fa-id-card text-primary fs-5 me-2"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Identificación</h6>

                                <small class="text-muted">
                                    Busque al paciente por su documento
                                </small>
                            </div>
                        </div>


                        <div class="row g-3">
                            {{-- TIPO DOCUMENTO --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">
                                    Tipo de documento
                                    <span class="text-danger">*</span>
                                </label>

                                <select class="form-control" name="tipo_identificacion" id="tipo_identificacion">
                                    <option value="DNI">DNI</option>
                                    <option value="CARNET EXTRANJERIA">CARNET EXTRANJERIA</option>
                                    <option value="PTP">PTP </option>
                                    <option value="TAM">TAM </option>
                                    <option value="RUC">RUC</option>
                                    <option value="PASAPORTE">PASAPORTE</option>
                                    <option value="SALVOCONDUCTO">SALVOCONDUCTO</option>
                                    <option value="SIN DOCUMENTOS">SIN DOCUMENTOS</option>
                                </select>
                                <span class="text-danger error-text tipo_identificacion_error"></span>
                            </div>


                            {{-- NUMERO DOCUMENTO --}}
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">
                                    Número de documento
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" name="numero_identidad"
                                        id="numero_identidad" placeholder="Ingrese el número de documento"
                                        autocomplete="off">
                                </div>

                                <small class="text-muted">
                                    Al ingresar el documento se buscarán los datos automáticamente.
                                </small>
                                <span class="text-danger error-text numero_identidad_error"></span>
                            </div>
                        </div>


                        {{-- RESULTADO DE BUSQUEDA --}}
                        <div id="resultadoPaciente" class="d-none mt-3">
                            <div class="alert alert-success mb-0">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check-circle fs-4 me-3"></i>
                                    <div>
                                        <div class="fw-bold">
                                            Paciente encontrado
                                        </div>
                                        <div id="nombrePacienteEncontrado"></div>
                                        <small>
                                            Los datos del paciente fueron cargados automáticamente.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>



                    {{-- ================================================= --}}
                    {{-- 2. DATOS PERSONALES --}}
                    {{-- ================================================= --}}

                    <div class="mb-4">
                        <div class="d-flex align-items-center border-bottom pb-2 mb-3">
                            <i class="fas fa-user text-primary fs-5 me-2"></i>
                            <div>

                                <h6 class="mb-0 fw-bold">Datos personales</h6>
                                <small class="text-muted">
                                    Información básica del paciente
                                </small>
                            </div>
                        </div>


                        <div class="row g-3">
                            {{-- NOMBRE --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">
                                    Nombre
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="nombre_paciente" id="nombre_paciente"
                                    placeholder="Nombre">
                                <span class="text-danger error-text nombre_paciente_error"></span>
                            </div>


                            {{-- APELLIDO PATERNO --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">
                                    Apellido paterno
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="apellido_paterno" id="apellido_paterno"
                                    placeholder="Apellido paterno">
                                <span class="text-danger error-text apellido_paterno_error"></span>
                            </div>


                            {{-- APELLIDO MATERNO --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">
                                    Apellido materno
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" name="apellido_materno" id="apellido_materno"
                                    placeholder="Apellido materno">
                                <span class="text-danger error-text apellido_materno_error"></span>
                            </div>
                        </div>
                    </div>



                    {{-- ================================================= --}}
                    {{-- 3. INFORMACIÓN GENERAL --}}
                    {{-- ================================================= --}}
                    <div class="mb-4">
                        <div class="d-flex align-items-center border-bottom pb-2 mb-3">
                            <i class="fas fa-clipboard-list text-primary fs-5 me-2"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Información general</h6>
                                <small class="text-muted">
                                    Información adicional del paciente
                                </small>
                            </div>
                        </div>


                        <div class="row g-3">
                            {{-- FECHA NACIMIENTO --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">
                                    Fecha de nacimiento
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" name="fecha_nacimiento"
                                    id="fecha_nacimiento">
                                <span class="text-danger error-text fecha_nacimiento_error"></span>
                            </div>


                            {{-- GENERO --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">
                                    Género
                                    <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" name="genero_paciente" id="genero_paciente">
                                    <option value="HOMBRE">Hombre</option>
                                    <option value="MUJER">Mujer </option>
                                </select>
                                <span class="text-danger error-text genero_paciente_error"></span>
                            </div>


                            {{-- ESTADO CIVIL --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Estado civil</label>
                                <select class="form-control" name="estado_civil" id="estado_civil">
                                    <option value="SOLTERO">SOLTERO</option>
                                    <option value="CASADO">CASADO</option>
                                    <option value="VIUDO">VIUDO</option>
                                    <option value="DIVORCIADO">DIVORCIADO</option>
                                </select>
                            </div>


                            {{-- OCUPACION --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Ocupación</label>
                                <input type="text" class="form-control" name="ocupacion" id="ocupacion"
                                    placeholder="Ocupación">
                            </div>

                            {{-- GRADO INSTRUCCION --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Grado de instrucción</label>
                                <input type="text" class="form-control" name="grado_instruccion"
                                    id="grado_instruccion" placeholder="Primaria, Secundaria, Superior">
                            </div>

                            {{-- EMAIL --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">
                                    Correo electrónico
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="email" class="form-control" name="email" id="email"
                                    placeholder="correo@ejemplo.com">
                                <span class="text-danger error-text email_error"></span>
                            </div>
                        </div>
                    </div>


                    {{-- ================================================= --}}
                    {{-- 4. CONTACTO --}}
                    {{-- ================================================= --}}

                    <div class="mb-4">
                        <div class="d-flex align-items-center border-bottom pb-2 mb-3">
                            <i class="fas fa-phone-alt text-primary fs-5 me-2"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Información de contacto</h6>
                                <small class="text-muted">
                                    Datos de comunicación y procedencia
                                </small>
                            </div>
                        </div>

                        <div class="row g-3">
                            {{-- TELEFONO --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Teléfono</label>
                                <input type="text" class="form-control" name="telefono" id="telefono"
                                    placeholder="999888777">
                            </div>

                            {{-- CANAL --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Canal </label>
                                <select class="form-control" name="channel_id" id="channel_id">
                                    @foreach ($channels as $channel)
                                        <option value="{{ $channel->id }}">
                                            {{ $channel->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>


                            {{-- MEDIO --}}
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Medio de interacción</label>
                                <select class="form-control" name="interaction_medium_id" id="interaction_medium_id">
                                    @foreach ($interaction_media as $medium)
                                        <option value="{{ $medium->id }}">
                                            {{ $medium->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>


                            {{-- DIRECCION --}}
                            <div class="col-md-6">

                                <label class="form-label fw-semibold">
                                    Dirección
                                </label>

                                <input type="text" class="form-control" name="direccion" id="direccion"
                                    placeholder="Av. Principal 123">

                            </div>


                            {{-- FAMILIAR --}}
                            <div class="col-md-6">

                                <label class="form-label fw-semibold">
                                    Familiar de contacto
                                </label>
                                <input type="text" class="form-control" name="familiar_contacto"
                                    id="familiar_contacto" placeholder="Nombre y relación">
                            </div>
                        </div>
                    </div>



                    {{-- ================================================= --}}
                    {{-- 5. RESPONSABLE --}}
                    {{-- ================================================= --}}

                    <div class="mb-2">

                        <div class="d-flex align-items-center border-bottom pb-2 mb-3">
                            <i class="fas fa-user-friends text-primary fs-5 me-2"></i>
                            <div>
                                <h6 class="mb-0 fw-bold">Responsable o acompañante </h6>
                                <small class="text-muted">Información opcional </small>
                            </div>
                        </div>


                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="responsable_id"
                                name="responsable_id" value="responsable">
                            <label class="form-check-label fw-semibold" for="responsable_id">
                                Registrar acompañante o responsable
                            </label>
                        </div>


                        <div class="oculto_card_responsable" id="modal_responsable">
                            <div class="border rounded p-3 bg-light">
                                <div class="row g-3">

                                    {{-- PARENTESCO --}}
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Parentesco</label>

                                        <select class="form-control" name="responsable_tipo" id="responsable_tipo">
                                            <option value="PAPA"> PAPÁ</option>
                                            <option value="MAMA">MAMÁ</option>
                                            <option value="HERMANO"> HERMANO</option>
                                            <option value="PRIMO">PRIMO</option>
                                            <option value="AMIGO">AMIGO</option>
                                            <option value="CONOCIDO CERCANO">CONOCIDO CERCANO</option>
                                        </select>
                                    </div>


                                    {{-- NOMBRE --}}
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Nombre completo</label>
                                        <input type="text" class="form-control" name="nombre_responsable"
                                            id="nombre_responsable" placeholder="Nombre del responsable">
                                    </div>


                                    {{-- TELEFONO --}}
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Teléfono</label>
                                        <input type="text" class="form-control" name="telefono_responsable"
                                            id="telefono_responsable" placeholder="999777666">
                                    </div>


                                    {{-- TIPO DOCUMENTO --}}
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Tipo de documento</label>

                                        <select class="form-control" name="tipo_identificacion_responsable"
                                            id="tipo_identificacion_responsable">
                                            <option value="DNI"> DNI</option>
                                            <option value="CARNET EXTRANJERIA"> CARNET EXTRANJERIA</option>
                                            <option value="PTP">PTP</option>
                                            <option value="TAM">TAM </option>
                                            <option value="RUC">RUC</option>
                                            <option value="PASAPORTE">PASAPORTE</option>
                                            <option value="SIN DOCUMENTOS"> SIN DOCUMENTOS</option>
                                        </select>
                                    </div>


                                    {{-- NUMERO DOCUMENTO --}}
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Número de documento</label>

                                        <input type="text" class="form-control"
                                            name="numero_identidad_responsable" id="numero_identidad_responsable"
                                            placeholder="12345678">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                {{-- ===================================================== --}}
                {{-- FOOTER --}}
                {{-- ===================================================== --}}

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <input type="submit" class="btn btn-primary btn-save" value="Guardar Paciente">
                </div>
            </form>
        </div>
    </div>
</div>
