<div class="modal fade" id="patientModalCreate" tabindex="-1" aria-labelledby="patientModalCreateLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="patientModalCreateLabel">Agregar nueva Cita:</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <form id="formCreateAppointment" action="{{ route('admissionit.appointment.store') }}" method="POST">

                    @csrf
                    <div class="row">

                        <!-- ================= PACIENTE ================= -->
                        <div class="col-12">
                            <h5 class="mb-1">Datos del Paciente</h5>
                        </div>

                        <input type="hidden" name="patient_id" id="patient_id">

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Nro Documento</label>
                                <input type="text" class="form-control" id="documento_paciente">
                            </div>
                        </div>

                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Paciente</label>
                                <input type="text" class="form-control" id="nombre_paciente" readonly>
                            </div>
                        </div>

                        <!-- ================= PROGRAMACION ================= -->

                        <div class="col-12 mt-2">
                            <h5>Programación de la Cita</h5>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Especialidad</label>
                                <select class="form-control" id="specialty_id">
                                    <option value="">Seleccione</option>
                                    @foreach ($specialties as $specialty)
                                        <option value="{{ $specialty->id }}">
                                            {{ $specialty->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Médico</label>
                                <select class="form-control" name="doctor_id" id="doctor_id">
                                    <option value="">Seleccione</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Servicio</label>
                                <select class="form-control" name="service_id" id="service_id">
                                    <option value="">
                                        Seleccione
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- ================= FECHA ================= -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Fecha Cita</label>
                                <input type="date" class="form-control" name="fecha_cita">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Hora Cita</label>
                                <input type="text" class="form-control" name="hora_cita">
                            </div>
                        </div>

                        <!-- ================= ORIGEN ================= -->
                        <div class="col-12 mt-2">
                            <h5>Origen de la Cita</h5>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Canal</label>
                                <select class="form-control" name="channel_id" id="channel_id">
                                    <option value="">Seleccione</option>
                                    @foreach ($channels as $channel)
                                        <option value="{{ $channel->id }}">
                                            {{ $channel->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Medio Interacción</label>
                                <select class="form-control" name="interaction_medium_id" id="interaction_medium_id">
                                    <option value="">Seleccione</option>
                                    @foreach ($interaction_media as $medium)
                                        <option value="{{ $medium->id }}">
                                            {{ $medium->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Tarifa Adicional</label>
                                <select class="form-control" name="additional_rate_id" id="additional_rate_id">
                                    @foreach ($additional_rates as $rate)
                                        <option value="{{ $rate->id }}">
                                            {{ $rate->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- ================= EXONERADO ================= -->
                        <div class="col-md-6 mt-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="es_exonerado" id="es_exonerado"
                                    value="1">
                                <label class="form-check-label">
                                    Exonerado (Paga Médico)
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <input type="text" class="form-control" name="autorizado_por" id="autorizado_por"
                                    placeholder="Autorizado por: Ej. DR QUIROZ">
                            </div>
                        </div>

                        <!-- ================= MOTIVO ================= -->
                        <div class="col-12 mt-2">
                            <div class="form-group">
                                <label>Motivo Consulta</label>
                                <textarea class="form-control" name="motivo_consulta" rows="3"></textarea>
                            </div>
                        </div>

                        <!-- ================= OBSERVACIONES ================= -->
                        <div class="col-12">
                            <div class="form-group">
                                <label>Observaciones</label>
                                <textarea class="form-control" name="observaciones" rows="3"></textarea>
                            </div>
                        </div>

                        <!-- ================= PAGO ================= -->
                        <div class="col-12 mt-2">
                            <h5>Información de Pago</h5>
                        </div>

                        <input type="hidden" name="precio_programado" id="precio_programado_hidden">
                        <input type="hidden" name="saldo_pendiente" id="saldo_pendiente_hidden">

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Precio Programado</label>
                                <input type="text" class="form-control" id="precio_programado" readonly>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Total Pagado</label>
                                <input type="number" step="0.01" class="form-control" name="total_pagado"
                                    id="total_pagado" value="0">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Saldo Pendiente</label>
                                <input type="text" class="form-control" id="saldo_pendiente" readonly>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Metodo de Pago</label>
                                <select class="form-control" name="metodo_pago" id="metodo_pago">
                                    <option value="YAPE">YAPE</option>
                                    <option value="PLIN">PLIN</option>
                                    <option value="EFECTIVO">EFECTIVO</option>
                                    <option value="TARJETA">TARJETA</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="form-group">
                                <label>N° Operación:</label>
                                <input type="number" class="form-control" name="numero_operacion"
                                    id="numero_operacion" value="0">
                            </div>
                        </div>



                        <!-- ================= BOTON ================= -->
                        <div class="col-12 text-end mt-4">
                            <input type="submit" class="btn btn-primary" value="Registrar Cita">
                        </div>

                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger light" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
