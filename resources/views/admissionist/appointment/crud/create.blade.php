<div class="modal fade" id="appointmentModalCreate" tabindex="-1" aria-labelledby="appointmentModalCreateLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-dark">
                <h5 class="modal-title text-white" id="appointmentModalCreateLabel">Agregar nueva Cita</h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>

            <form id="formCreateAppointment" action="{{ route('admissionit.appointment.store') }}" method="POST">

                @csrf

                <div class="modal-body">

                    <input type="hidden" name="patient_id" id="patient_id">

                    <!-- ================= DATOS PACIENTE ================= -->
                    <h6 class="fw-bold mb-3">Datos del Paciente</h6>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label text-primary">Nro Documento <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="documento_paciente">

                            <span class="text-danger error-text patient_id_error"></span>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-primary">Paciente <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre_paciente" readonly>
                        </div>

                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cita_doble">

                                <label class="form-check-label" for="cita_doble">
                                    Generar turno doble
                                </label>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- ================= PROGRAMACION ================= -->
                    <h6 class="fw-bold mb-3">Programación de la Cita</h6>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label text-primary">Especialidad <span
                                    class="text-danger">*</span></label>
                            <select class="form-control" id="specialty_id">
                                <option value="">
                                    Seleccione
                                </option>

                                @foreach ($specialties as $specialty)
                                    <option value="{{ $specialty->id }}">
                                        {{ $specialty->nombre }}
                                    </option>
                                @endforeach
                            </select>

                            <span class="text-danger error-text specialty_id_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Médico <span class="text-danger">*</span></label>
                            <select class="form-control" name="doctor_id" id="doctor_id">
                                <option value="">
                                    Seleccione
                                </option>
                            </select>

                            <span class="text-danger error-text doctor_id_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Servicio <span class="text-danger">*</span></label>
                            <select class="form-control" name="service_id" id="service_id">
                                <option value="">
                                    Seleccione
                                </option>
                            </select>
                            <span class="text-danger error-text service_id_error"></span>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-primary">Fecha Cita <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="fecha_cita" id="fecha_cita">
                            <span class="text-danger error-text fecha_cita_error"></span>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-primary">Hora Cita <span class="text-danger">*</span></label>
                            {{--  <input type="text" class="form-control" name="hora_cita" placeholder="HH:mm"> --}}
                            <select class="form-control" name="hora_cita" id="hora_cita">
                                <option value="">Seleccione una hora</option>
                            </select>
                            <span class="text-danger error-text hora_cita_error"></span>
                        </div>
                    </div>

                    <hr>

                    <!-- ================= ORIGEN Y CONDICIONES ================= -->
                    <h6 class="fw-bold mb-3">Origen y Condiciones</h6>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label text-primary">Tarifa Especial <span
                                    class="text-danger">*</span></label>
                            <select class="form-control" name="additional_rate_id" id="additional_rate_id">
                                @foreach ($additional_rates as $rate)
                                    <option value="{{ $rate->id }}">
                                        {{ $rate->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="es_exonerado" id="es_exonerado"
                                    value="1">
                                <label class="form-check-label" for="es_exonerado">
                                    Exonerado (Paga Médico)
                                </label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Autorizado por</label>
                            <input type="text" class="form-control" name="autorizado_por" id="autorizado_por"
                                placeholder="Ej. DR QUIROZ">
                        </div>
                    </div>

                    <hr>

                    <!-- ================= PAGO ================= -->
                    <h6 class="fw-bold mb-3">Información de Pago</h6>

                    <input type="hidden" name="precio_programado" id="precio_programado_hidden">
                    <input type="hidden" name="saldo_pendiente" id="saldo_pendiente_hidden">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label text-primary">Precio Programado</label>
                            <input type="text" class="form-control" id="precio_programado" readonly>
                            <span class="text-danger error-text precio_programado_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Total Pagado <span
                                    class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="total_pagado"
                                id="total_pagado" value="0">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Saldo Pendiente <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="saldo_pendiente" readonly>
                            <span class="text-danger error-text saldo_pendiente_error"></span>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-primary">Método de Pago</label>
                            <select class="form-control" name="metodo_pago" id="metodo_pago">
                                <option value="YAPE">YAPE</option>
                                <option value="PLIN">PLIN</option>
                                <option value="EFECTIVO">EFECTIVO</option>
                                <option value="TARJETA">TARJETA</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-primary">N° Operación</label>
                            <input type="text" class="form-control" name="numero_operacion" id="numero_operacion"
                                value="0">
                        </div>
                    </div>

                    <hr>

                    <!-- ================= OBSERVACIONES ================= -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-primary">Motivo Consulta</label>
                            <textarea class="form-control" name="motivo_consulta" rows="4"></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-primary">Observaciones</label>
                            <textarea class="form-control" name="observaciones" rows="4"></textarea>
                        </div>
                    </div>


                    <!-- ================= PARA LA FOTO SACAR EL N° DE OPERACION ================= -->
                    <div class="row g-3">
                        <div class="card">
                            <div class="drop" id="dropZone">
                                <svg width="34" height="34" viewBox="0 0 24 24" fill="none"
                                    stroke="#2a9d96" stroke-width="1.6">
                                    <path d="M12 16V4M12 4l-4 4M12 4l4 4" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                </svg>
                                <p class="principal">Arrastra la captura de pago aquí</p>
                                <p class="secundario">o haz clic para elegir un archivo</p>
                                <input type="file" id="fileInput" accept="image/*">
                            </div>

                            <div class="preview" id="preview" style="display:none">
                                <img id="previewImg" alt="Captura cargada">
                            </div>

                            <div class="estado" id="estado"></div>

                            <div class="resultado" id="resultado">
                                <label>Número de operación detectado</label>
                                {{--
                                <div class="caja-numero">
                                    <span id="numeroDetectado">—</span>
                                    <button class="btn-copiar" id="btnCopiar">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2">
                                            <rect x="9" y="9" width="12" height="12" rx="2" />
                                            <path d="M5 15V5a2 2 0 0 1 2-2h10" stroke-linecap="round" />
                                        </svg>
                                        <span id="btnCopiarTexto">Copiar</span>
                                    </button>
                                </div>
                                --}}
                            </div>

                            <details>
                                <summary>Ver texto reconocido (para revisar si algo falla)</summary>
                                <pre id="debugTexto">—</pre>
                            </details>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="modal-footer bg-dark">
                    <button type="button" class="btn btn-dark btn-rounded" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <input type="submit" class="btn btn-primary btn-save btn-rounded" value="Registrar Cita">
                </div>

            </form>

        </div>
    </div>


</div>
