<div class="modal fade" id="appointmentModalEdit" tabindex="-1" aria-labelledby="appointmentModalEditLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-dark">
                <h5 class="modal-title text-white" id="appointmentModalEditLabel">Actualizar la Cita</h5>

                <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>

            <form id="formUpdateSchedule" action="{{ route('admissionit.schedule.update') }}" method="POST">

                @csrf

                <div class="modal-body">

                    <input type="hidden" name="appointment_id" id="appointment_id">

                    <!-- ================= DATOS PACIENTE ================= -->
                    <h6 class="fw-bold mb-3">Datos del Paciente</h6>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label text-primary">Nro Documento <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="documento_paciente_edit" readonly>
                        </div>

                        <div class="col-md-9">
                            <label class="form-label text-primary">Paciente <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre_paciente_edit" readonly>
                        </div>
                    </div>

                    <hr>

                    <!-- ================= PROGRAMACION ================= -->
                    <h6 class="fw-bold mb-3">Programación de la Cita</h6>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label text-primary">Especialidad <span
                                    class="text-danger">*</span></label>
                            <select class="form-control" id="specialty_id_edit">
                                <option value="">
                                    Seleccione
                                </option>

                                @foreach ($specialties as $specialty)
                                    <option value="{{ $specialty->id }}">
                                        {{ $specialty->nombre }}
                                    </option>
                                @endforeach
                            </select>

                            <span class="text-danger error-text specialty_id_edit_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Médico <span class="text-danger">*</span></label>
                            <select class="form-control" name="doctor_id_edit" id="doctor_id_edit">
                            </select>

                            <span class="text-danger error-text doctor_id_edit_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Servicio <span class="text-danger">*</span></label>
                            <select class="form-control" name="service_id_edit" id="service_id_edit">
                            </select>

                            <span class="text-danger error-text service_id_edit_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Fecha Cita <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="fecha_cita_edit" id="fecha_cita_edit">

                            <span class="text-danger error-text fecha_cita_edit_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Hora Cita <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="hora_cita_edit" id="hora_cita_edit"
                                placeholder="HH:mm">

                            <span class="text-danger error-text hora_cita_edit_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Estado de la Cita <span
                                    class="text-danger">*</span></label>
                            <select class="form-control" name="estado_cita" id="estado_cita">
                                <option value="PROGRAMADO">PROGRAMADO</option>
                                <option value="CONFIRMADO">CONFIRMADO</option>
                                <option value="EN_ESPERA">EN ESPERA</option>
                                <option value="LLAMANDO">LLAMANDO</option>
                                <option value="EN_ATENCION">EN ATENCION</option>
                                <option value="ATENDIDO">ATENDIDO</option>
                                <option value="CANCELADO">CANCELADO</option>
                                <option value="NO_ASISTIO">NO ASISTIO</option>
                            </select>

                            <span class="text-danger error-text estado_cita_edit_error"></span>
                        </div>
                    </div>

                    <hr>

                    <!-- ================= OBSERVACIONES ================= -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-primary">Motivo Consulta</label>
                            <textarea class="form-control" name="motivo_consulta_edit" rows="4"></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-primary">Observaciones</label>
                            <textarea class="form-control" name="observaciones_edit" rows="4"></textarea>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="modal-footer bg-dark">
                    <button type="button" class="btn btn-dark btn-rounded" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <input type="submit" class="btn btn-primary btn-save btn-rounded" value="Actualizar Cita">
                </div>

            </form>

        </div>
    </div>


</div>
