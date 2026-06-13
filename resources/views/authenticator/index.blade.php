@extends('layouts.app')


@section('body')

<body class="vh-100">
    <div class="authincation h-100">
        <div class="container h-100">
            <div class="row justify-content-center h-100 align-items-center">
                <div class="col-md-6">
                    <div class="authincation-content">
                        <div class="row no-gutters">
                            <div class="col-xl-12">
                                <div class="auth-form">
                                    <div class="text-center mb-3">
                                        <a href="index.html"><img src="{{ asset('assets/images/logo-full.png') }}" class="img-fluid" alt=""></a>
                                    </div>
                                    <h4 class="text-center mb-4">Ingreso al Sistema</h4>

                                     {{-- MENSAJE SI ESTAN MAL LAS CREDENCIALES --}}
                                    @if (session('mensaje'))
                                        <div class="alert alert-danger" role="alert">
                                            {{ session('mensaje') }}
                                        </div>
                                    @endif

                                    <form action="{{ route('admin.login.store') }}" method="POST">

                                        @csrf
                                        <div class="form-group">
                                            <label class="mb-1"><strong>Correo</strong></label>
                                            <input type="email" name="email" id="email" class="form-control" placeholder="ejemplo@ceosalud.com">
                                        </div>

                                        <div class="mb-3 position-relative">
                                            <label class="mb-1"><strong>Contraseña</strong></label>
                                            <input type="password" name="password" id="password" class="form-control" placeholder="*****">
                                            <span class="show-pass eye">
                                                <i class="fa fa-eye-slash"></i>
                                                <i class="fa fa-eye"></i>
                                            </span>
                                        </div>

                                        <div class="form-row d-flex justify-content-between flex-wrap mt-4 mb-2">
                                            <div class="form-group">
                                                <div class="form-check custom-checkbox ms-1">
                                                    <input type="checkbox" class="form-check-input"
                                                        id="basic_checkbox_1">
                                                    <label class="form-check-label" for="basic_checkbox_1">Recodar</label>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <a href="#">No recuerda contraseña?</a>
                                            </div>
                                        </div>

                                        <div class="text-center">
                                            <input type="submit" class="btn btn-primary btn-block" value="Ingresar al Sistema">
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!--**********************************
        Scripts
    ***********************************-->
    <!-- Required vendors -->

    <script src="{{ asset('assets/vendor/global/global.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>
    <script src="{{ asset('assets/js/custom.min.js') }}"></script>
    <script src="{{ asset('assets/js/deznav-init.js') }}"></script>





</body>

@endsection