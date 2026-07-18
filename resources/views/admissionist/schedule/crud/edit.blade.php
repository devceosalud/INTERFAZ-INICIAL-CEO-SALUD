<div class="modal fade" id="doctorScheduleModalEdit" tabindex="-1" aria-labelledby="doctorScheduleModalEditLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header bg-dark">
                <h5 class="modal-title text-white" id="doctorScheduleModalEditLabel">
                    Actualizar horario
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>

            <form id="formUpdateDoctorSchedule" method="POST" action="{{ route('admissionit.doctor.schedule.update') }}">

                @method('put')
                @csrf

                <div class="modal-body">

                    <!-- DATOS PERSONALES -->
                    <h6 class="fw-bold mb-3">
                        Datos Personales
                    </h6>

                    <div class="row g-3">
                        <input type="hidden" name="doctor_schedule_id_edit" id="doctor_schedule_id_edit">

                        <div class="col-md-3">
                            <label class="form-label text-primary">Doctor</label>
                            <select class="form-control" name="doctor_id_edit" id="doctor_id_edit">
                                @foreach ($doctors as $doctor)
                                    <option value="{{ $doctor->id }}"> {{ $doctor->nombre }} </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <input type="hidden">
                            <label class="form-label text-primary">día de la semana</label>
                            <select class="form-control" name="dia_semana_edit" id="dia_semana_edit">
                                <option value="1">Lunes</option>
                                <option value="2">Martes</option>
                                <option value="3">Miércoles</option>
                                <option value="4">Jueves</option>
                                <option value="5">Viernes</option>
                                <option value="6">Sábado</option>
                                <option value="1">Domingo</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label text-primary">Hora inico</label>
                            <input type="time" class="form-control" name="hora_inicio_edit" id="hora_inicio_edit"
                                placeholder="08:00">

                            <span class="text-danger error-text hora_inicio_edit_error"></span>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label text-primary">Hora fin</label>
                            <input type="time" class="form-control" name="hora_fin_edit" id="hora_fin_edit"
                                placeholder="20:00">

                            <span class="text-danger error-text hora_fin_edit_error"></span>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label text-primary">duración</label>
                            <select class="form-control" name="duracion_edit_cita" id="duracion_edit_cita">
                                @for ($i = 10; $i <= 60; $i++)
                                    <option value="{{ $i }}"> {{ $i }} min</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="modal-footer bg-dark">
                    <button type="button" class="btn btn-dark btn-rounded" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <input type="submit" class="btn btn-primary btn-save btn-rounded" value="Actualizar Horario">
                </div>
            </form>

        </div>
    </div>

    <script>
        flatpickr("#hora_inicio_edit, #hora_fin_edit", {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
        });
    </script>
</div>
