@extends('layouts.app')


@section('css_data')
    <!-- Datatable -->
    <link href="{{ asset('assets/vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
    <!-- Custom Stylesheet -->
    <link href="{{ asset('assets/vendor/bootstrap-select/dist/css/bootstrap-select.min.css') }}" rel="stylesheet">

    <link href="{{ asset('assets/vendor/fullcalendar/css/main.min.css') }}" rel="stylesheet">
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
                    <div class="col-xl-12 col-xxl-12">
                        <div
                            class="mx-auto d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                            <h4 class="card-title">App\Calendario</h4>

                            <div id="external-events" class="">
                                {{-- <a href="javascript:void(0);" class="btn btn-primary btn-rounded add-appointment"
                                    data-bs-toggle="modal" data-bs-target="#appointmentModalCreate">+ Agregar
                                    Cita</a>
                                    --}}
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-12 col-xxl-12 mt-2">
                        <div class="card">
                            <div class="card-body">
                                <div id="calendar" class="app-fullcalendar"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- row -->
                <div class="row">

                    <div class="col-12">
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
                                                    <td><strong>{{ $appointment->numero_cita }}</strong></td>
                                                    <td>{{ $appointment->patient->nombre }}</td>
                                                    <td>{{ $appointment->doctor->nombre }}</td>
                                                    <td>{{ $appointment->service->nombre }}</td>
                                                    <td>{{ $appointment->fecha_cita }} {{ $appointment->hora_cita }}</td>
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
                                                            {{--
                                                            <span class="me-3">
                                                                <a href="#" class="edit-patient"
                                                                    data-id="{{ $appointment->id }}">
                                                                    <i class="fa fa-pencil fs-18 text-success"></i>
                                                                </a>
                                                            </span>
                                                            --}}
                                                            <span>
                                                                <i class="fa fa-trash fs-18 text-danger"></i>
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
        </div>
        <!--**********************************Content body end***********************************-->

        <!--**********************************Scripts***********************************-->
        @section('script_data')
            <!-- Required vendors -->
            <script src="{{ asset('assets/vendor/global/global.min.js') }}"></script>
            <script src="{{ asset('assets/vendor/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>

            <script src="{{ asset('assets/vendor/moment/moment.min.js') }}"></script>
            <script src="{{ asset('assets/vendor/fullcalendar/js/main.min.js') }}"></script>
            <script src="{{ asset('assets/js/plugins-init/fullcalendar-init.js') }}"></script>

            <!-- Datatable -->
            <script src="{{ asset('assets/vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
            <script src="{{ asset('assets/js/plugins-init/datatables.init.js') }}"></script>
            <script src="{{ asset('assets/js/custom.min.js') }}"></script>
            <script src="{{ asset('assets/js/deznav-init.js') }}"></script>

            <script src="{{ asset('js/admissionist/appointment/appointment.js') }}"></script>
            <script src="{{ asset('js/admissionist/schedule/schedule.js') }}"></script>
        @endsection


        <!--**********************************Footer start***********************************-->
        @include('templates.footer')
        <!--**********************************Footer end***********************************-->

        <!--**********************************Support ticket button start***********************************-->

        <!--**********************************Support ticket button end***********************************-->


    </div>
    <!--**********************************Main wrapper end***********************************-->

@endsection
