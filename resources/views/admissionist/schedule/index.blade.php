@extends('layouts.app')


@section('css_data')
    <!-- Datatable -->
    <link href="{{ asset('assets/vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
    <!-- Custom Stylesheet -->
    <link href="{{ asset('assets/vendor/bootstrap-select/dist/css/bootstrap-select.min.css') }}" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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

                    <div class="col-12">
                        <div class="card">
                            <div
                                class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                <h4 class="card-title">Horario</h4>

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
                                                        <button
                                                            class="accordion-button {{ $loop->first ? '' : 'collapsed' }}"
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
                                                                                <i
                                                                                    class="fa fa-pencil fs-18 text-success"></i>
                                                                            </a>
                                                                        </span>
                                                                        <span>
                                                                            <a href="#" class="delete-doctor-schedule"
                                                                                data-id="{{ $horario->id }}">
                                                                                <i
                                                                                    class="fa fa-trash fs-18 text-danger"></i>
                                                                            </a>
                                                                        </span>
                                                                    </strong>
                                                                    <span>
                                                                        <strong>
                                                                            @switch($horario->dia_semana)
                                                                                @case(1)
                                                                                    Lunes
                                                                                @break

                                                                                @case(2)
                                                                                    Martes
                                                                                @break

                                                                                @case(3)
                                                                                    Miércoles
                                                                                @break

                                                                                @case(4)
                                                                                    Jueves
                                                                                @break

                                                                                @case(5)
                                                                                    Viernes
                                                                                @break

                                                                                @case(6)
                                                                                    Sábado
                                                                                @break

                                                                                @default
                                                                                    Domingo
                                                                            @endswitch
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
                                            <!-- cierra accordion accordion-primary -->
                                        </div>
                                        <!-- cierra tab-pane -->
                                    </div>

                                    {{--
                                <div class="table-responsive">
                                    <table id="example4" class="display" style="min-width: 845px">
                                        <thead>
                                            <tr>
                                                <th>Código</th>
                                                <th>Doctor</th>
                                                <th>Día</th>
                                                <th>Hora incio</th>
                                                <th>Hora fin</th>
                                                <th>Duración</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($doctor_schedules as $doctor_schedule)
                                                <tr>
                                                    <td><strong>{{ $doctor_schedule->id }}</strong></td>
                                                    <td>{{ $doctor_schedule->doctor->nombre }}</td>
                                                    <td>

                                                        @switch($doctor_schedule->dia_semana)
                                                            @case(1)
                                                                <strong><span>Lunes</span></strong>
                                                            @break

                                                            @case(2)
                                                                <strong><span>Martes</span></strong>
                                                            @break

                                                            @case(3)
                                                                <strong><span>Miércoles</span></strong>
                                                            @break

                                                            @case(4)
                                                                <strong><span>Jueves</span></strong>
                                                            @break

                                                            @case(5)
                                                                <strong><span>Viernes</span></strong>
                                                            @break

                                                            @case(6)
                                                                <strong><span>Sábado</span></strong>
                                                            @break

                                                            @default
                                                                <strong><span>Domingo</span></strong>
                                                        @endswitch

                                                    </td>
                                                    <th>{{ $doctor_schedule->hora_inicio }}</th>
                                                    <th>{{ $doctor_schedule->hora_fin }}</th>
                                                    <td>{{ $doctor_schedule->duracion_cita }}</td>
                                                    <td>
                                                        <strong>
                                                            <span class="me-3">
                                                                <a href="#" class="edit-doctor-schedule"
                                                                    data-id="{{ $doctor_schedule->id }}">
                                                                    <i class="fa fa-pencil fs-18 text-success"></i>
                                                                </a>
                                                            </span>
                                                            <span>
                                                                <a href="#" class="delete-doctor-schedule"
                                                                    data-id="{{ $doctor_schedule->id }}">
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
                                --}}
                                </div>
                            </div>
                        </div>


                        @include('admissionist.schedule.crud.create')

                        @include('admissionist.schedule.crud.edit');

                    </div>
                </div>
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
