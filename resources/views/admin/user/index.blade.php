@extends('layouts.app')


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

        <div class="content-body">
            <div class="container-fluid">
                {{--
                <div class="page-titles">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="javascript:void(0)">Table</a>
                            </li>
                            <li class="breadcrumb-item active">
                                <a href="javascript:void(0)">Permisos</a>
                            </li>
                        </ol>

                        <a href="{{ route('admin.permissions.create') }}" class="btn btn-primary btn-rounded">
                            + Agregar Permiso
                        </a>
                    </div>
                </div>
                --}}
                <!-- row -->
                <div class="row">

                    <div class="col-12">
                        <div class="card">
                            <div
                                class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                <h4 class="card-title">Lista de Usuarios</h4>

                                <a href="javascript:void(0);" class="btn btn-primary btn-rounded add-appointment"
                                    data-bs-toggle="modal" data-bs-target="#userModalCreate">
                                    + Agregar Usuario
                                </a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">

                                    @if (session('exito'))
                                        <div class="alert alert-success solid alert-end-icon alert-dismissible fade show">
                                            <span><i class="mdi mdi-check"></i></span>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"
                                                aria-label="btn-close">
                                                <span><i class="fa-solid fa-xmark"></i></span>
                                            </button>

                                            {{ session('exito') }}
                                        </div>
                                    @endif

                                    <table id="example4" class="table table-bordered text-center mb-0" id="datatable">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>NOMBRE</th>
                                                <th>EMAIL</th>
                                                <th>ROL</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($users as $user)
                                                <tr>
                                                    <td>{{ $user->id }}</td>
                                                    <td>{{ $user->name }}</td>
                                                    <td>{{ $user->email }}</td>
                                                    <td>
                                                        @forelse ($user->getRoleNames() as $role)
                                                            <span
                                                                class="badge light badge-success">{{ $role }}</span>
                                                        @empty
                                                            <span class="badge light badge-success">sin rol</span>
                                                        @endforelse
                                                    </td>
                                                    <td>
                                                        <strong>
                                                            <span class="me-3">
                                                                <a href="#" class="edit-user"
                                                                    data-id="{{ $user->id }}">
                                                                    <i class="fa fa-pencil fs-18 text-success"></i>
                                                                </a>
                                                            </span>
                                                            <span>
                                                                <a class="btn btn-primary btn-rounded"
                                                                    href="{{ route('admin.user.edit', ['user' => $user]) }}">Asignar
                                                                    Rol</a>
                                                            </span>
                                                        </strong>

                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td>Sin Usuarios por ahora</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    @include('admin.user.crud.create')

                    @include('admin.user.crud.edit')
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

            <script src="{{ asset('js/admin/master/user/user.js') }}"></script>
        @endsection



        <!--**********************************Footer start***********************************-->
        @include('templates.footer')
        <!--**********************************Footer end***********************************-->

        <!--**********************************Support ticket button start***********************************-->

        <!--**********************************Support ticket button end***********************************-->


    </div>
    <!--**********************************Main wrapper end***********************************-->


@endsection
