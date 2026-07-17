@extends('layouts.app')

@php
    use Carbon\Carbon;
@endphp

@section('css_data')
    <!-- Datatable -->
    <link href="{{ asset('assets/vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
    <!-- Custom Stylesheet -->
    <link href="{{ asset('assets/vendor/bootstrap-select/dist/css/bootstrap-select.min.css') }}" rel="stylesheet">
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
        <style>
            .oculto_card_responsable {
                display: none;
            }
        </style>

        <div class="content-body">
            <div class="container-fluid">

                <!-- row -->
                <div class="row">

                    <div class="col-12">
                        <div class="card">
                            <div
                                class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                <h4 class="card-title">Lista de Pacientes</h4>

                                <a href="javascript:void(0);" class="btn btn-primary btn-rounded add-appointment"
                                    data-bs-toggle="modal" data-bs-target="#patientModalCreate">
                                    + Agregar Paciente
                                </a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="example4" class="display" style="min-width: 845px">
                                        <thead>
                                            <tr>
                                                <th>HC</th>
                                                <th>Nombres</th>
                                                <th>Documento</th>
                                                <th>Edad</th>
                                                <th>Celular </th>
                                                <th>Genero </th>
                                                <th>Fecha Nacimiento</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($patients as $patient)
                                                <tr>
                                                    <td><strong>{{ $patient->id }}</strong></td>
                                                    <td>{{ $patient->nombre }} {{ $patient->apellido_paterno }}</td>
                                                    <td>{{ $patient->numero_identidad }}</td>
                                                    <td>{{ Carbon::parse($patient->fecha_nacimiento)->age }}</td>
                                                    <td>{{ $patient->telefono }}</td>
                                                    <td>
                                                        @if ($patient->genero == 'HOMBRE')
                                                            <span class="badge light badge-success">HOMBRE</span>
                                                        @else
                                                            <span class="badge light badge-warning">MUJER</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $patient->fecha_nacimiento }}</td>
                                                    <td>
                                                        <strong>
                                                            <span class="me-3">
                                                                <a href="#" class="edit-patient"
                                                                    data-id="{{ $patient->id }}">
                                                                    <i class="fa fa-pencil fs-18 text-success"></i>
                                                                </a>
                                                            </span>
                                                            <span>
                                                                <a href="#" class="delete-patient"
                                                                    data-id="{{ $patient->id }}">
                                                                    <i class="fa fa-trash fs-18 text-danger"></i>
                                                                </a>

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

            @include('admissionist.patient.crud.create')

            @include('admissionist.patient.crud.edit')

            @include('admissionist.patient.modal.modal-agendar')

            @include('admissionist.patient.modal.appointment')
        </div>
        <!--**********************************Content body end***********************************-->


        <!--**********************************Scripts***********************************-->

    @section('script_data')
        <!-- Required vendors -->
        <script src="{{ asset('assets/vendor/global/global.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>


        <!-- Datatable -->
        <script src="{{ asset('assets/vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
        <script src="{{ asset('assets/js/plugins-init/datatables.init.js') }}"></script>
        <script src="{{ asset('assets/js/custom.min.js') }}"></script>
        <script src="{{ asset('assets/js/deznav-init.js') }}"></script>


        <script src="{{ asset('js/admissionist/patient/patient.js') }}"></script>
    @endsection



    <!--**********************************Footer start***********************************-->
    @include('templates.footer')
    <!--**********************************Footer end***********************************-->

    <!--**********************************Support ticket button start***********************************-->

    <!--**********************************Support ticket button end***********************************-->


</div>
<!--**********************************Main wrapper end***********************************-->


@endsection
