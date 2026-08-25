<div>
    <div class="row">

        <div class="col-12">
            <div class="card">
                <div
                    class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                    <h4 class="card-title">Horarios disponibles</h4>
                </div>
                <div class="card-body">

                    {{-- FILTROS --}}
                    <div class="row mb-3" id="available-schedule">
                        <div class="col-md-3">
                            <label class="form-label text-primary">Especialidad <span
                                    class="text-danger">*</span></label>
                            <select class="form-control" id="available-schedule_specialty_id">
                                <option value="">Seleccione</option>

                                @foreach ($specialties as $specialty)
                                    <option value="{{ $specialty->id }}">
                                        {{ $specialty->nombre }}
                                    </option>
                                @endforeach
                            </select>

                            <span class="text-danger error-text specialty_id_error"></span>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label text-primary">Médico <span class="text-danger">*</span></label>
                            <select class="form-control" name="available-schedule_doctor_id"
                                id="available-schedule_doctor_id">
                                <option value="">Seleccione</option>
                            </select>

                            <span class="text-danger error-text doctor_id_error"></span>
                        </div>

                        {{-- <div class="col-md-3">
                            <label class="form-label text-primary">Servicio <span class="text-danger">*</span></label>
                            <select class="form-control" name="available-schedule_service_id"
                                id="available-schedule_service_id">
                                <option value="">
                                    Seleccione
                                </option>
                            </select>
                            <span class="text-danger error-text service_id_error"></span>
                        </div>--}}

                        <div class="col-md-3">
                            <label class="form-label text-primary">Fecha Cita <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="available-schedule_fecha_cita"
                                id="available-schedule_fecha_cita">
                            <span class="text-danger error-text fecha_cita_error"></span>
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
                    {{-- FILTROS --}}


                    {{-- LISTA DE HORARIOS DISPONIBLES --}}
                    <div class="table-responsive">
                        <table  class="display" style="min-width: 845px">
                            <thead>
                                <tr>
                                    <th>Horarios disponibles</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpo-tabla">
                                <!-- Aquí se pintarán los datos con JS -->
                            </tbody>
                        </table>
                    </div>
                    {{-- LISTA DE HORARIOS DISPONIBLES --}}
                </div>
            </div>
        </div>
    </div>
</div>
