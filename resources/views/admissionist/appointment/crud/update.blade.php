<div class="modal fade" id="appointmentModalState-{{$appointment->id}}" tabindex="-1" aria-labelledby="appointmentModalStateLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header bg-dark">
                <h5 class="modal-title text-white" id="appointmentModalStateLabel">Actualizar estado de la Cita</h5>

                <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>

            <form id="" action="{{ route('admissionit.appointment.update') }}" method="POST">

                @csrf

                <div class="modal-body">

                    <input type="hidden" name="appointment_id" id="appointment_id" value="{{ $appointment->id }}">


                    <!-- ================= PROGRAMACION ================= -->
                    <h6 class="fw-bold mb-3">Programación de la Cita</h6>

                    <div class="row g-3">
                        
                        <div class="col-md-12">
                            <label class="form-label text-primary">Estado de la Cita <span
                                    class="text-danger">*</span></label>
                            <select class="form-control" name="estado_cita" id="estado_cita">
                                <option value="PROGRAMADO">PROGRAMADO</option>
                                <option value="CONFIRMADO">CONFIRMADO</option>
                                <option value="PACIENTE_LLEGO">PACIENTE LLEGO</option>
                                <option value="EN_ESPERA">EN ESPERA</option>
                                <option value="LLAMANDO">LLAMANDO</option>
                                <option value="EN_ATENCION">EN ATENCION</option>
                                <option value="ATENDIDO">ATENDIDO</option>
                                <option value="REEVALUACION">REEVALUACION</option>
                                <option value="CANCELADO">CANCELADO</option>
                                <option value="NO_ASISTIO">NO ASISTIO</option>
                            </select>

                            <span class="text-danger error-text estado_cita_edit_error"></span>
                        </div>
                    </div>

                    <hr>

                </div>

                <!-- FOOTER -->
                <div class="modal-footer bg-dark">
                    <button type="button" class="btn btn-dark btn-rounded" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <input type="submit" class="btn btn-primary btn-save btn-rounded" value="Actualizar Estado">
                </div>

            </form>

        </div>
    </div>


</div>
