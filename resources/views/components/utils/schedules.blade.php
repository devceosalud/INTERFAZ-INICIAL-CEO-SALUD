<div>
    <div class="row">

        <div class="col-12">
            <div class="card">
                <div
                    class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                    <h4 class="card-title">Horarios</h4>

                    <a href="javascript:void(0);" class="btn btn-primary btn-rounded add-appointment"
                        data-bs-toggle="modal" data-bs-target="#doctorScheduleModalCreate">
                        + Agregar Horario
                    </a>
                </div>

                <div class="card-body">
                    {{-- LOS FILTRO PARA CALENDARIO MEDICO --}}
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <label class="form-label text-primary">Especialidad <span
                                    class="text-danger">*</span></label>
                            <select class="form-control" id="filtro-calendar-medico_specialty_id">
                                <option value="">Seleccione</option>

                                @foreach ($specialties as $specialty)
                                    <option value="{{ $specialty->id }}">
                                        {{ $specialty->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="text-danger error-text specialty_id_error"></span>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-primary">Médico <span class="text-danger">*</span></label>
                            <select class="form-control" name="filtro-calendar-medico_doctor_id"
                                id="filtro-calendar-medico_doctor_id">
                                <option value="">Seleccione</option>
                            </select>
                            <span class="text-danger error-text doctor_id_error"></span>
                        </div>
                    </div>
                    {{-- LOS FILTRO PARA CALENDARIO MEDICO --}}


                    {{-- CALENDARIO MEDICO --}}
                    <div id="calendar-medico"></div>
                    {{-- CALENDARIO MEDICO --}}


                    {{-- LISTA DE LOS HORARIOS MEDICOS --}}
                    <div class="tab-content mt-4" id="myTabContent">
                        <div class="tab-pane fade show active" id="Preview" role="tabpanel"
                            aria-labelledby="home-tab">
                            <div class="accordion accordion-primary" id="accordion-doctores">
                                @foreach ($doctors as $doctor)
                                    @php
                                        $collapseId = 'collapse-doctor-' . $doctor->id;
                                    @endphp
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}"
                                                type="button" data-bs-toggle="collapse"
                                                data-bs-target="#{{ $collapseId }}"
                                                aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                                                aria-controls="{{ $collapseId }}">
                                                {{ $doctor->nombre }}
                                            </button>
                                        </h2>

                                        <div id="{{ $collapseId }}"
                                            class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}"
                                            data-bs-parent="#accordion-doctores">
                                            <div class="accordion-body">
                                                {{--
                                                    Los horarios heredados se registran por fecha concreta. La tabla
                                                    lo muestra tal cual en lugar de presentarlos como una recurrencia
                                                    semanal, que la lógica heredada no mantiene.
                                                --}}
                                                @php
                                                    $bloques = $doctor->schedules
                                                        ->where('estado', 'ACTIVO')
                                                        ->sortBy([['fecha_cita', 'asc'], ['hora_inicio', 'asc']]);
                                                @endphp

                                                @if ($bloques->isEmpty())
                                                    <p class="text-muted mb-0">Sin horario registrados</p>
                                                @else
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-hover mb-0 align-middle">
                                                            <thead>
                                                                <tr>
                                                                    <th scope="col">Fecha</th>
                                                                    <th scope="col">Turno</th>
                                                                    <th scope="col">Inicio</th>
                                                                    <th scope="col">Fin</th>
                                                                    <th scope="col">Duración</th>
                                                                    <th scope="col">Cupos</th>
                                                                    <th scope="col">Sede</th>
                                                                    <th scope="col">Estado</th>
                                                                    <th scope="col" class="text-end">Acciones</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($bloques as $horario)
                                                                    @php
                                                                        $inicio = substr($horario->hora_inicio, 0, 5);
                                                                        $fin = substr($horario->hora_fin, 0, 5);
                                                                        $minutosBloque = max(
                                                                            0,
                                                                            (strtotime($fin) - strtotime($inicio)) / 60,
                                                                        );
                                                                        $cupos = $horario->duracion_cita > 0
                                                                            ? (int) floor($minutosBloque / $horario->duracion_cita)
                                                                            : 0;
                                                                        $turno = $inicio < '12:00' ? 'Mañana' : ($inicio < '18:00' ? 'Tarde' : 'Noche');
                                                                    @endphp
                                                                    <tr>
                                                                        <td>
                                                                            @if ($horario->fecha_cita)
                                                                                {{ \Carbon\Carbon::parse($horario->fecha_cita)->format('d/m/Y') }}
                                                                                <small class="d-block text-muted">
                                                                                    {{ \App\Models\DoctorSchedule::DIAS[\Carbon\Carbon::parse($horario->fecha_cita)->dayOfWeekIso] }}
                                                                                </small>
                                                                            @else
                                                                                <span class="badge light badge-info">
                                                                                    Cada
                                                                                    {{ \App\Models\DoctorSchedule::DIAS[$horario->dia_semana] ?? 'sin día' }}
                                                                                </span>
                                                                            @endif
                                                                        </td>
                                                                        <td>{{ $turno }}</td>
                                                                        <td><strong>{{ $inicio }}</strong></td>
                                                                        <td><strong>{{ $fin }}</strong></td>
                                                                        <td>{{ $horario->duracion_cita }} min</td>
                                                                        <td>{{ $cupos }}</td>
                                                                        <td>
                                                                            {{ $horario->site ? $horario->site->nombre : '—' }}
                                                                        </td>
                                                                        <td>
                                                                            <span class="badge light badge-success">
                                                                                {{ $horario->estado }}
                                                                            </span>
                                                                        </td>
                                                                        <td class="text-end text-nowrap">
                                                                            <a href="#"
                                                                                class="edit-doctor-schedule btn btn-sm btn-primary"
                                                                                data-id="{{ $horario->id }}"
                                                                                aria-label="Editar horario">
                                                                                <i class="fa fa-pencil fs-18"></i>
                                                                            </a>
                                                                            <a href="#"
                                                                                class="delete-doctor-schedule btn btn-sm btn-danger"
                                                                                data-id="{{ $horario->id }}"
                                                                                aria-label="Inactivar horario">
                                                                                <i class="fa fa-trash fs-18"></i>
                                                                            </a>
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    {{-- LISTA DE LOS HORARIOS MEDICOS --}}

                </div>
            </div>
        </div>
    </div>
</div>
