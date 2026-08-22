<div>
    <div class="row">

        <div class="col-12">
            <div class="card">
                <div
                    class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                    <h4 class="card-title">Reevaluaciones: {{ Date('Y-m-d') }} </h4>

                    {{-- <a href="javascript:void(0);" class="btn btn-primary btn-rounded add-appointment"
                                    data-bs-toggle="modal" data-bs-target="#appointmentModalCreate">+ Agregar Cita</a>
                                    --}}
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="example4" class="display" style="min-width: 845px">
                            <thead>
                                <tr>
                                    <th>Estado</th>
                                    <th>Paciente</th>
                                    <th>Medico</th>
                                    <th>Servicio</th>
                                    <th>Citado </th>
                                    <th>Pago </th>
                                    <th>Debe</th>
                                    <th>X</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reevaluaciones as $appointment)
                                    <tr>
                                        <td><strong>{{ $appointment->estado_cita }}</strong></td>
                                        <td><span class="small">{{ $appointment->patient->nombre }}</span>
                                        </td>
                                        <td><span class="small">{{ $appointment->doctor->nombre }}</span>
                                        </td>
                                        <td><span
                                                class="bage light badge-danger small"><strong>{{ $appointment->service->nombre }}</strong></span>
                                        </td>
                                        <td><span class="small"><strong>{{ $appointment->fecha_cita }}</strong></span>
                                            <span class="badge light badge-primary">{{ $appointment->hora_cita }}</span>
                                        </td>
                                        <td>
                                            @switch($appointment->estado_pagado)
                                                @case('PARCIAL')
                                                    <span
                                                        class="badge light badge-warning">{{ $appointment->estado_pagado }}</span>
                                                @break

                                                @case('PENDIENTE')
                                                    <span
                                                        class="badge light badge-danger">{{ $appointment->estado_pagado }}</span>
                                                @break

                                                @default
                                                    <span
                                                        class="badge light badge-success">{{ $appointment->estado_pagado }}</span>
                                            @endswitch
                                        </td>
                                        <td>{{ $appointment->saldo_pendiente }} </td>
                                        <td>

                                            <strong>
                                                <span class="me-3">
                                                    <a href="#" class="update-appointment"
                                                        data-id="{{ $appointment->id }}"><i
                                                            class="fa fa-pencil fs-18 text-success"></i></a>
                                                </span>
                                            </strong>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
