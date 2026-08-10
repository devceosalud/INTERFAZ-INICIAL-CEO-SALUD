@extends('layouts.app')


@section('css_data')
    <!-- Datatable -->
    <link href="{{ asset('assets/vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
    <!-- Custom Stylesheet -->
    <link href="{{ asset('assets/vendor/bootstrap-select/dist/css/bootstrap-select.min.css') }}" rel="stylesheet">

    {{-- <link href="{{ asset('assets/vendor/fullcalendar/css/main.min.css') }}" rel="stylesheet"> --}}

    <!-- STYLESHEETS CALENDAR-->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@7.0.1/skeleton.css' rel='stylesheet' />
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@7.0.1/themes/monarch/theme.css' rel='stylesheet' />
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@7.0.1/themes/monarch/palettes/purple.css' rel='stylesheet' />

    <link href="{{ asset('css/tesseract.css') }}" rel="stylesheet">
@endsection



@section('body')
    <!--*******************Preloader start********************-->
    @include('templates.preloader')
    <!--*******************Preloader end********************-->

    <!--**********************************Main wrapper start***********************************-->
    <div id="main-wrapper">

        <!--**********************************Nav header start***********************************-->
        @include('templates.nav-header')
        <!--**********************************Nav header end***********************************-->

        <!--**********************************Chat box start***********************************-->
        @include('templates.chat-box')
        <!--**********************************Chat box End***********************************-->

        <!--**********************************Header start***********************************-->
        @include('templates.header')
        <!--**********************************Header end ti-comment-alt***********************************-->

        <!--**********************************Sidebar start***********************************-->
        @include('templates.sidebar')
        <!--**********************************Sidebar end***********************************-->


        <!--**********************************Content body start***********************************-->
        <div class="content-body">
            <div class="container-fluid">

                <!-- row -->
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label text-primary">Especialidad <span class="text-danger">*</span></label>
                        <select class="form-control" id="filtro-calendar_specialty_id">
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
                        <select class="form-control" name="filtro-calendar_doctor_id" id="filtro-calendar_doctor_id">
                            <option value="">Seleccione</option>
                        </select>

                        <span class="text-danger error-text doctor_id_error"></span>
                    </div>


                    <div class="col-xl-12 col-xxl-12 mt-2">
                        <div class="calendar-container">
                            <div id="calendar"></div>
                        </div>
                    </div>
                </div>

                <!-- row -->
                <div class="row">

                    <div class="col-12 mt-4">
                        <div class="card">
                            <div
                                class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                <h4 class="card-title">Lista de Citas</h4>

                                {{-- <a href="javascript:void(0);" class="btn btn-primary btn-rounded add-appointment"
                                    data-bs-toggle="modal" data-bs-target="#appointmentModalCreate">+ Agregar Cita</a>
                                    --}}
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="example4" class="display" style="min-width: 845px">
                                        <thead>
                                            <tr>
                                                <th>Cita</th>
                                                <th>Paciente</th>
                                                <th>Medico</th>
                                                <th>Servicio</th>
                                                <th>Cita </th>
                                                <th>Pago </th>
                                                <th>Debe</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($appointments as $appointment)
                                                <tr>
                                                    <td><strong>{{ $appointment->estado_cita }}</strong></td>
                                                    <td>{{ $appointment->patient->nombre }}</td>
                                                    <td>{{ $appointment->doctor->nombre }}</td>
                                                    <td>{{ $appointment->service->nombre }}</td>
                                                    <td>{{ $appointment->fecha_cita }} {{ $appointment->hora_cita }}
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

            @include('admissionist.appointment.crud.create')

            @include('admissionist.appointment.crud.edit')

            @include('admissionist.appointment.crud.update')
        </div>
        <!--**********************************Content body end***********************************-->

        <!--**********************************Scripts***********************************-->
        @section('script_data')
            <!-- Required vendors -->
            <script src="{{ asset('assets/vendor/global/global.min.js') }}"></script>
            <script src="{{ asset('assets/vendor/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>

            <script src="{{ asset('assets/vendor/moment/moment.min.js') }}"></script>
            {{-- <script src="{{ asset('assets/vendor/fullcalendar/js/main.min.js') }}"></script>
            <script src="{{ asset('assets/js/plugins-init/fullcalendar-init.js') }}"></script> --}}

            <!-- STANDARD JS -->
            <script src="https://cdn.jsdelivr.net/npm/fullcalendar@7.0.1/all/global.js"></script>
            <!-- THEME JS -->
            <script src="https://cdn.jsdelivr.net/npm/fullcalendar@7.0.1/themes/monarch/global.js"></script>

            <!-- Datatable -->
            <script src="{{ asset('assets/vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
            <script src="{{ asset('assets/js/plugins-init/datatables.init.js') }}"></script>
            <script src="{{ asset('assets/js/custom.min.js') }}"></script>
            <script src="{{ asset('assets/js/deznav-init.js') }}"></script>

            <!-- TESSERACT -->
            <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>

            <script src="{{ asset('js/admissionist/appointment/appointment.js') }}"></script>
            <script src="{{ asset('js/admissionist/schedule/schedule.js') }}"></script>

            <script src="{{ asset('js/admissionist/filtro-calendario/filtro-calendario.js') }}"></script>
            <script src="{{ asset('js/admissionist/calendario/calendario.js') }}"></script>

            <script src="{{ asset('js/admissionist/appointment/editar-cita.js') }}"></script>
            <script src="{{ asset('js/admissionist/appointment/update.js') }}"></script>

            <script src="{{ asset('js/admissionist/tesseract/tesseract.js') }}"></script>
        @endsection


        <!--**********************************Footer start***********************************-->
        @include('templates.footer')
        <!--**********************************Footer end***********************************-->

        <!--**********************************Support ticket button start***********************************-->

        <!--**********************************Support ticket button end***********************************-->


    </div>
    <!--**********************************Main wrapper end***********************************-->

@endsection
