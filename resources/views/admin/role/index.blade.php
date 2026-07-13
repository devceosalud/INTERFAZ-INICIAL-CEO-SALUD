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
   
                <!-- row -->
                <div class="row">

                    <div class="col-12">
                        <div class="card">
                            <div
                                class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                <h4 class="card-title">Lista de Roles</h4>

                                <a href="{{ route('admin.permissions.create') }}" class="btn btn-primary btn-rounded">
                                    + Agregar Permiso
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
                                                <th>PERMISOS</th>

                                                {{-- <th>ELIMINAR</th> --}}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($roles as $role)
                                                <tr>
                                                    <td>{{ $role->id }}</td>
                                                    <td>{{ $role->name }}</td>

                                                    <td>
                                                        <a class="btn btn-primary btn-rounded"
                                                            href="{{ route('admin.roles.edit', ['role' => $role]) }}">Asignar
                                                            Permisos
                                                        </a>
                                                    </td>

                                                    {{--
                                                    <td>
                                                        <form action="{{ route('admin.roles.destroy', ['role' => $role]) }}"
                                                            method="POST">
                                                            @csrf
                                                            @method('delete')
                                                            <button class="btn btn-dark">Eliminar</button>
                                                        </form>
                                                    </td>
                                                    --}}
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td>Sin Roles por ahora</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

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
        @endsection



        <!--**********************************Footer start***********************************-->
        @include('templates.footer')
        <!--**********************************Footer end***********************************-->

        <!--**********************************Support ticket button start***********************************-->

        <!--**********************************Support ticket button end***********************************-->


    </div>
    <!--**********************************Main wrapper end***********************************-->


@endsection
