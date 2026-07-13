@extends('layouts.app')

@section('body')

    <body class="vh-100 bg-white">

        <div class="container-fluid h-100">
            <div class="row h-100">

                <!-- LEFT: FORM -->
                <div class="col-lg-5 d-flex align-items-center justify-content-center">

                    <div class="w-75">

                        <!-- LOGO -->
                        <div class="mb-4 text-start">
                            <img src="{{ asset('assets/images/logo-full.png') }}" width="160" alt="">
                        </div>

                        <h3 class="fw-bold mb-2">
                            Inicia sesión en tu cuenta
                        </h3>

                        <p class="text-muted mb-4">
                            Accede a la plataforma
                        </p>

                        {{-- ERROR --}}
                        @if (session('mensaje'))
                            <div class="alert alert-danger">
                                {{ session('mensaje') }}
                            </div>
                        @endif

                        <form action="{{ route('admin.login.store') }}" method="POST">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label">Correo electrónico</label>
                                <input type="email" name="email" class="form-control" placeholder="ejemplo@correo.com">                               
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Contraseña</label>
                                <input type="password" name="password" class="form-control" placeholder="********">
                            </div>

                            <div class="d-flex justify-content-between mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember">
                                    <label class="form-check-label" for="remember">
                                        Recuérdame
                                    </label>
                                </div>

                                <a href="#" class="text-primary">
                                    ¿Olvidaste tu contraseña?
                                </a>
                            </div>

                            <button class="btn btn-primary w-100 py-2">
                                Iniciar sesión
                            </button>
                        </form>

                    </div>

                </div>

                <!-- RIGHT: IMAGE -->
                <div class="col-lg-7 d-none d-lg-block p-0">

                    <div class="h-100 w-100"
                        style="
                    background-image: url('{{ asset('assets/images/sidebar-img/6.jpg') }}');
                    background-size: cover;
                    background-position: center;
                ">
                    </div>

                </div>

            </div>
        </div>

        <script src="{{ asset('assets/vendor/global/global.min.js') }}"></script>
        <script src="{{ asset('assets/js/custom.min.js') }}"></script>

    </body>
@endsection
