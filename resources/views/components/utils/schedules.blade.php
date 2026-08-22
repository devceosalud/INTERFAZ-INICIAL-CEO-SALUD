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

                    <div class="tab-content" id="myTabContent">
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
                                                @forelse ($doctor->schedules->where('estado','ACTIVO') as $horario)
                                                    <p>
                                                        <strong>
                                                            <span class="me-3">
                                                                <a href="#" class="edit-doctor-schedule"
                                                                    data-id="{{ $horario->id }}">
                                                                    <i class="fa fa-pencil fs-18 text-success"></i>
                                                                </a>
                                                            </span>
                                                            <span>
                                                                <a href="#" class="delete-doctor-schedule"
                                                                    data-id="{{ $horario->id }}">
                                                                    <i class="fa fa-trash fs-18 text-danger"></i>
                                                                </a>
                                                            </span>
                                                        </strong>
                                                        <span>
                                                            <strong>
                                                                @php
                                                                    $dias = [
                                                                        1 => 'Lunes',
                                                                        2 => 'Martes',
                                                                        3 => 'Miércoles',
                                                                        4 => 'Jueves',
                                                                        5 => 'Viernes',
                                                                        6 => 'Sábado',
                                                                        7 => 'Domingo',
                                                                    ];

                                                                    $dia = $dias[$horario->dia_semana] ?? 'Sin día';
                                                                @endphp
                                                                {{ $dia }}
                                                            </strong>
                                                        </span>
                                                        <span class="badge light badge-success">de
                                                            {{ $horario->hora_inicio }}</span>
                                                        <span class="badge light badge-warning">hasta
                                                            {{ $horario->hora_fin }}</span>
                                                        <span><strong>{{ $horario->duracion_cita }}
                                                                minutos</strong></span>
                                                    </p>
                                                @empty
                                                    <p class="text-muted mb-0">Sin horario registrados</p>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
