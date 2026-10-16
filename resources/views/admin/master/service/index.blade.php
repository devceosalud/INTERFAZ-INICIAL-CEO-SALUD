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
                                <h4 class="card-title">Catálogo de servicios y precios institucionales</h4>

                                <a href="javascript:void(0);" class="btn btn-primary btn-rounded add-appointment"
                                    data-bs-toggle="modal" data-bs-target="#serviceModalCreate">
                                    + Agregar Servicio
                                </a>
                            </div>
                            <div class="card-body"><a class="btn btn-outline-primary mb-3" href="{{ route('master.service.doctor.index') }}">Precios y asignaciones por profesional</a><p>Precio institucional: común para profesionales habilitados. En consultas, un precio particular tiene prioridad. Los recargos se administran por separado.</p>
                                <div class="table-responsive">
                                    <table id="example4" class="display" style="min-width: 845px">
                                        <thead>
                                            <tr>
                                                <th>Codigo</th>
                                                <th>Especialidad</th>
                                                <th>Nombre</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($services as $service)
                                                <tr>
                                                    <td><strong>{{ $service->id }}</strong></td>
                                                    <td><strong>{{ $service->specialty->nombre }}</strong> </td>
                                                    <td>{{ $service->nombre }}<br><small>{{ $service->estado }} · {{ $catalogTypes[$service->service_type] ?? 'Tipo pendiente' }} · {{ $service->pricing_policy === 'INSTITUTIONAL' ? 'Institucional' : ($service->pricing_policy === 'PROFESSIONAL' ? 'Por profesional' : 'Heredado por profesional') }}@if($service->institutional_price !== null) · S/ {{ $service->institutional_price }}@endif</small><br><small>{{ $service->doctorServices->where('estado','ACTIVO')->count() }} asignaciones activas: {{ $service->doctorServices->where('estado','ACTIVO')->map(fn($row)=>$row->doctor?->nombre)->filter()->join(', ') ?: 'ninguna' }} @if($service->canonical_service_id) · Consulta canónica #{{ $service->canonical_service_id }}@endif</small></td>
                                                    <td>
                                                        <strong>
                                                            <span class="me-3">
                                                                <a href="#" class="edit-service btn btn-sm btn-primary"
                                                                    data-id="{{ $service->id }}">
                                                                    <i class="fa fa-pencil fs-18"></i>
                                                                </a>
                                                            </span>
                                                            <span>
                                                                <a href="#" class="delete-service btn btn-sm btn-danger" data-id="{{ $service->id }}" data-version="{{ $service->catalog_version }}">
                                                                    <i class="fa fa-trash fs-18"></i>
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

            @include('admin.master.service.crud.create')

            @include('admin.master.service.crud.edit')

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
        <script src="{{ \App\Support\Scheduling\AgendaAssets::url('js/admin/master/service/service.js') }}"></script>
    @endsection



    <!--**********************************Footer start***********************************-->
    @include('templates.footer')
    <!--**********************************Footer end***********************************-->

    <!--**********************************Support ticket button start***********************************-->

    <!--**********************************Support ticket button end***********************************-->


</div>
<!--**********************************Main wrapper end***********************************-->


@endsection
