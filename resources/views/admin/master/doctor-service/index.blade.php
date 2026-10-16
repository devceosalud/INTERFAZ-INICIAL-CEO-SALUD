@extends('layouts.app')


@section('css_data')
 <link href="{{ \App\Support\Scheduling\AgendaAssets::url('css/admin/catalog.css') }}" rel="stylesheet">
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
        <div class="content-body">
            <div class="container-fluid">
                <!-- row -->
                <div class="row">

                    <div class="col-12">
                        <div class="card">
                            <div
                                class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                <h4 class="card-title">Servicios y precios por profesional</h4>

                                <a href="javascript:void(0);" class="btn btn-primary btn-rounded add-appointment"
                                    data-bs-toggle="modal" data-bs-target="#doctorServiceModalCreate">
                                    + Agregar Profesional/Servicio
                                </a>
                            </div>
                            <div class="card-body">
 <div class="d-flex flex-wrap gap-2 mb-3"><a class="btn btn-outline-primary" href="{{ route('master.service.index') }}">Catálogo y precios institucionales</a><a class="btn btn-outline-secondary" href="{{ route('master.additionalRate.index') }}">Recargos opcionales</a></div>
 @if(session('catalog_saved'))<div class="alert alert-success" role="status">{{ session('catalog_saved') }}</div>@endif
 <p>Selecciona un profesional para consultar sus asignaciones activas e inactivas. Editar precios no modifica citas existentes.</p>
 <form method="GET" class="row g-2 align-items-end mb-3"><div class="col-md-8"><label for="catalog-professional" class="form-label">Profesional</label><select name="doctor_id" id="catalog-professional" class="form-control"><option value="">Todos los profesionales</option>@foreach($doctors as $doctor)<option value="{{ $doctor->id }}" @selected((string)$selectedDoctorId === (string)$doctor->id)>{{ $doctor->nombre }} · {{ $doctor->estado }}</option>@endforeach</select></div><div class="col-md-4"><button class="btn btn-primary" type="submit">Ver servicios y precios</button></div></form>
                                <div class="table-responsive">
                                    <table id="example4" class="display" style="min-width: 845px">
                                        <thead>
                                            <tr>
                                                <th>Codigo</th>
                                                <th>Profesional</th>
                                                <th>Servicio</th>
                                                <th>Consulta / precio base S/</th>
                                                <th>Reevaluación S/</th>
                                                <th>Plazo heredado</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($doctorServices as $doctorService)
                                                <tr>
                                                    <td><strong>{{ $doctorService->id }}</strong></td>
                                                    <td><strong>{{ $doctorService->doctor?->nombre ?? 'Profesional no disponible' }}</strong> </td>
                                                    <td>{{ $doctorService->service?->nombre ?? 'Servicio no disponible' }}<br><small>{{ $doctorService->service?->pricing_policy === 'INSTITUTIONAL' ? 'Precio institucional: S/ '.$doctorService->service->institutional_price : 'Precio por profesional' }} · {{ $doctorService->estado }}</small>@if(!app(\App\Services\Catalog\ServiceSpecialtyEligibility::class)->allowed($doctorService))<br><strong class="text-danger">Incompatible con la especialidad o registro inactivo. Revisar autorización.</strong>@endif</td>
                                                    <td>{{ $doctorService->service?->pricing_policy === 'INSTITUTIONAL' ? $doctorService->service->institutional_price : ($doctorService->precio_primera_consulta ?? $doctorService->service?->institutional_price ?? 'Sin configurar') }}</td>
                                                    <td>{{ $doctorService->precio_reconsulta ?? 'Sin configurar' }}</td>
                                                    <td><span class="badge light badge-success">{{ $doctorService->dias_reconsulta}}</span></td>
                                                    <td>
                                                        <strong>
                                                            <span class="me-3">
                                                                <a href="#" class="edit-doctor-service"
                                                                    data-id="{{ $doctorService->id }}">
                                                                    <i class="fa fa-pencil fs-18 text-success"></i>
                                                                </a>
                                                            </span>
                                                            <span>
                                                                <a href="#" class="delete-doctor-service" data-id="{{ $doctorService->id }}" data-version="{{ $doctorService->catalog_version }}">
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

            @include('admin.master.doctor-service.crud.create')
@include('admin.master.doctor-service.crud.edit')


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

        <script src="{{ \App\Support\Scheduling\AgendaAssets::url('js/admin/master/catalog-editor.js') }}"></script>
        <script src="{{ \App\Support\Scheduling\AgendaAssets::url('js/admin/master/doctor-service/doctor-service.js') }}"></script>
    @endsection



    <!--**********************************Footer start***********************************-->
    @include('templates.footer')
    <!--**********************************Footer end***********************************-->

    <!--**********************************Support ticket button start***********************************-->

    <!--**********************************Support ticket button end***********************************-->


</div>
<!--**********************************Main wrapper end***********************************-->


@endsection
