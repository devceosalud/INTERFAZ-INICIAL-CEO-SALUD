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

                        <a href="{{ route('admin.roles.index') }}" class="btn btn-primary btn-rounded add-appointment">
                            Ver Roles
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
                                <h4 class="card-title">Roles</h4>

                                <a href="{{ route('admin.roles.index') }}"
                                    class="btn btn-primary btn-rounded add-appointment">
                                    Ver Roles
                                </a>
                            </div>
                            <div class="card-body">

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

                                <div class="col-lg-12 table-responsive mb-5">
                                    {!! Form::model($user, ['route' => ['admin.user.update', $user], 'method' => 'put']) !!}

                                    {!! Form::label('text', 'Usuario escogido', ['class' => 'control-label mb-2']) !!}
                                    {!! Form::text('nombre', $user->name, ['class' => 'form-control mb-4', 'placeholder' => 'Nombre del usuario']) !!}

                                    @foreach ($roles as $role)
                                        <div>
                                            <label class="d-flex">
                                                {!! Form::radio('role', $role->id, $user->hasRole($role->id), ['class' => 'mr-1']) !!}
                                                {{ $role->name }}
                                            </label>
                                            @if ($role->name === 'MEDICO' && $user->hasRole('ADMINISTRADOR'))
                                                <p id="medical-role-additive-note" class="alert alert-info text-dark mt-2" role="note">
                                                    Agregar MEDICO conserva ADMINISTRADOR y sus permisos existentes.
                                                    El acceso médico solo funcionará tras vincular y verificar su identidad en Vinculación médica.
                                                    Otro administrador debe aprobar el cambio; se invalidarán las sesiones de esta cuenta.
                                                </p>
                                            @elseif ($role->name === 'MEDICO' && $user->roles->contains(fn($current) => $current->name !== 'MEDICO'))
                                                <p id="medical-role-incompatible-warning" class="alert alert-warning text-dark mt-2" role="note">
                                                    Esta cuenta tiene roles distintos de ADMINISTRADOR o MEDICO:
                                                    <strong>{{ $user->roles->pluck('name')->implode(', ') }}</strong>.
                                                    No se quitarán esos roles automáticamente para agregar MEDICO. Verifica los accesos antes de continuar.
                                                </p>
                                            @endif
                                        </div>
                                    @endforeach

                                    @if ($user->hasRole('MEDICO') && $user->hasRole('ADMINISTRADOR'))
                                        <p class="alert alert-warning text-dark mt-2" role="note">
                                            Esta cuenta posee MEDICO y ADMINISTRADOR. Elegir un rol distinto de MEDICO
                                            sustituirá los roles actuales y puede retirar el acceso clínico.
                                        </p>
                                        <label class="d-block mt-2">
                                            <input type="checkbox" name="confirm_remove_medico" value="1">
                                            Confirmo expresamente que quiero retirar MEDICO si selecciono otro rol.
                                        </label>
                                    @endif

                                    {!! Form::submit('Asignar Rol al usuario', ['class' => 'btn btn-primary mt-4 btn-rounded']) !!}

                                    {!! Form::close() !!}
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
