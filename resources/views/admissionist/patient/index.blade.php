@extends('layouts.app')

@php
    use Carbon\Carbon;
@endphp

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
        <style>
            .oculto_card_responsable {
                display: none;
            }
        </style>
        <div class="content-body">
            <!-- row -->
            <div class="container-fluid">
                <div class="form-head d-flex mb-3 mb-md-4 justify-content-between w-100">
                    <div class="">
                        <a href="javascript:void(0);" class="btn btn-primary btn-rounded add-appointment"
                            data-bs-toggle="modal" data-bs-target="#patientModalCreate">+ Agregar Paciente</a>
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
                                                <th>Nombre y Apellidos</th>
                                                <th>Nro.HC</th>
                                                <th>Tipo iden.</th>
                                                <th>Nro documento</th>
                                                <th>Sexo</th>
                                                <th>Edad</th>
                                                <th class="text-end">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                            @foreach ($patients as $patient)
                                                <tr>
                                                    <td class="patient-info ps-0">
                                                        <span class="text-nowrap ms-2"> {{ $patient->nombre }} </span>
                                                    </td>
                                                    <td class="text-primary"> {{ $patient->historia_clinica }} </td>
                                                    <td class="text-primary"> {{ $patient->tipo_identificacion }} </td>
                                                    <td> {{ $patient->numero_identidad }} </td>
                                                    <td>
                                                        @if ($patient->genero == 'HOMBRE')
                                                            <button class="btn btn-primary">HOMBRE</button>
                                                        @else
                                                            <button class="btn btn-danger">MUJER</button>
                                                        @endif

                                                    </td>
                                                    <td> {{ Carbon::parse($patient->fecha_nacimiento)->age }} </td>
                                                    <td class="text-end">
                                                        <span class="me-3">
                                                            <a href="#" class="edit-patient"
                                                                data-id="{{ $patient->id }}">
                                                                <i class="fa fa-pencil fs-18 text-success"></i>
                                                            </a>
                                                        </span>
                                                        <span>
                                                            <i class="fa fa-trash fs-18 text-danger"></i>
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

            @include('admissionist.patient.crud.create')

            @include('admissionist.patient.crud.edit')
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

<script src="{{ asset('js/admissionist/patient/patient.js') }}"></script>
@endsection
