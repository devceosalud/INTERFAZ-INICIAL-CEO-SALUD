@extends('layouts.app')


@section('body')
    <!--*******************
                                                                                            Preloader start
                                                                                        ********************-->
    @include('templates.preloader')
    <!--*******************
                                                                                            Preloader end
                                                                                        ********************-->

    <!--**********************************
                                                                                            Main wrapper start
                                                                                        ***********************************-->
    <div id="main-wrapper">

        <!--**********************************
                                                                                                Nav header start
                                                                                            ***********************************-->
        @include('templates.nav-header')
        <!--**********************************
                                                                                                Nav header end
                                                                                            ***********************************-->

        <!--**********************************
                                                                                                Chat box start
                                                                                            ***********************************-->
        @include('templates.chat-box')
        <!--**********************************
                                                                                                Chat box End
                                                                                            ***********************************-->

        <!--**********************************
                                                                                                Header start
                                                                                            ***********************************-->
        @include('templates.header')
        <!--**********************************
                                                                                                Header end ti-comment-alt
                                                                                            ***********************************-->

        <!--**********************************
                                                                                                Sidebar start
                                                                                            ***********************************-->
        @include('templates.sidebar')
        <!--**********************************
                                                                                                Sidebar end
                                                                                            ***********************************-->


        <!--**********************************
                                                                    Content body start
                                                                ***********************************-->
        <div class="content-body">
            <!-- row -->
            <div class="container-fluid">
                <div class="form-head d-flex mb-3 mb-md-4 justify-content-between w-100">
                    <div>
                        <a href="javascript:void(0);" class="btn btn-primary btn-rounded add-appointment"
                            data-bs-toggle="modal" data-bs-target="#patientModalCreate">+ Agregar Cita</a>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table id="datatable"
                                        class="table table-striped patient-list mb-4 dataTablesCard fs-14">
                                        <thead>
                                            <tr>
                                                <th>Paciente</th>
                                                <th>Medico</th>
                                                <th>Especilidad</th>
                                                <th>Canal</th>
                                                <th>Fecha de la Cita</th>
                                                <th>Celular</th>
                                                <th>Pago</th>
                                                <th>Debe</th>
                                                <th>Servicios</th>
                                                <th>Estado</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($appointments as $appointment)
                                                <tr>
                                                    <td class="patient-info ps-0">
                                                        <span class="text-nowrap ms-2"> {{ $appointment->patient->nombre }}
                                                        </span>
                                                    </td>
                                                    <td class="text-primary"> {{ $appointment->doctor->nombre }} </td>
                                                    <td> {{ $appointment->doctor->specialty->nombre }}</td>
                                                    <td> {{ $appointment->channel->nombre }} </td>
                                                    <td> {{ $appointment->fecha_cita }} {{ $appointment->hora_cita }} </td>
                                                    <td class="text-primary"> {{ $appointment->patient->telefono }} </td>
                                                    <td>
                                                        @switch($appointment->estado_pagado)
                                                            @case('PARCIAL')
                                                                <button
                                                                    class="btn btn-warning">{{ $appointment->estado_pagado }}</button>
                                                            @break

                                                            @case('PENDIENTE')
                                                                <button
                                                                    class="btn btn-danger">{{ $appointment->estado_pagado }}</button>
                                                            @break

                                                            @default
                                                                <button
                                                                    class="btn btn-primary">{{ $appointment->estado_pagado }}</button>
                                                        @endswitch
                                                    </td>
                                                    <td> {{ $appointment->saldo_pendiente }} </td>
                                                    <td> {{ $appointment->service->nombre }}</td>
                                                    <td> {{ $appointment->estado_cita }} </td>
                                                    <td>
                                                        {{--
                                                        <span class="me-3">
                                                            <a href="javascript:void(0);" class="edit-appointment"><i
                                                                    class="fa fa-pencil fs-18 " aria-hidden="true"></i></a>
                                                        </span>
                                                        --}}
                                                        <span>
                                                            <i class="fa fa-trash fs-18 text-danger" aria-hidden="true"></i>
                                                        </span>
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
        </div>
        <!--**********************************
                                                                    Content body end
                                                                ***********************************-->


        <!--**********************************
                                                                                                        Scripts
                                                                                                    ***********************************-->
        <!-- Required vendors -->
        @section('script_data')
            <script src="{{ asset('assets/vendor/global/global.min.js') }}"></script>



            <!-- Dashboard 1 -->
            <script src="{{ asset('assets/js/custom.min.js') }}"></script>
            <script src="{{ asset('assets/js/deznav-init.js') }}"></script>
        @endsection


        <!--**********************************
                                                                        Footer start
                                                                    ***********************************-->
        @include('templates.footer')
        <!--**********************************
                                                                        Footer end
                                                                    ***********************************-->

        <!--**********************************
                                                                       Support ticket button start
                                                                    ***********************************-->

        <!--**********************************
                                                                       Support ticket button end
                                                                    ***********************************-->


    </div>
    <!--**********************************
                                                                    Main wrapper end
                                                                ***********************************-->

    <script src="{{ asset('js/admissionist/appointment/appointment.js') }}"></script>
@endsection
