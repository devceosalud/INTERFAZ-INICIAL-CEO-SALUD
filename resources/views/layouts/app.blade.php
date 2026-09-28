<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Title -->
    <title>CEOSALUD</title>

    {{-- CON ESTE COMANDO SE ARREGLO ERROR: 419 --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Meta -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="author" content="DexignZone">
    <meta name="robots" content="">

    <meta name="keywords"
        content="sistema clinico erp, erp  administrativo, gestión de pacientes, gestión de  citas, gestión de usuarios.">
    <meta name="description"
        content="Sistema responsivo y adaptable para todo tipo ded pantallas, con diferentes tecnologias involucradas.">

    <meta property="og:title" content="ERP Ceo Salud - Hospital administrativo multirol.">
    <meta property="og:description"
        content="Sistema responsivo y adaptable para todo tipo ded pantallas, con diferentes tecnologias involucradas.">
    <meta property="og:image" content="https://eres.dexignzone.com/xhtml/social-image.png">
    <meta name="format-detection" content="telephone=no">

    <!-- Mobile Specific -->
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Favicon icon -->
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/images/logo-full.png') }}">
    <link href="https://cdn.lineicons.com/2.0/LineIcons.css" rel="stylesheet">


    @yield('css_data')


    <!-- Style Css -->
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">

    <!-- DATATABLES CSS
    <link rel="stylesheet" href="{{ asset('assets/lib/datatable/dataTables.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/lib/datatable/dataTables.min.css') }}">
    -->

    <!-- CDN JQUERY -->
    <script src="https://code.jquery.com/jquery-2.2.4.min.js"
        integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44=" crossorigin="anonymous"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .bg-primary {
            background-color: #2d8a8c !important;
        }
         .fc-monarch-y37 {
            background-color: #2d8a8c;
        }

        .fc-monarch-SJz:hover {
            Background-color: #2d8a8c;
        }

        .fc-monarch-4os {
            background-color: #2d8a8c;
        }
    </style>
    <!--ESTILOS LIVEWIRE"-->
    @livewireStyles
</head>

<body>

    @yield('body')

    <script>
        // Las APIs internas usan la misma sesión web y protección CSRF que Blade.
        // Mantiene compatibles los fetch existentes sin enviar el token a otros orígenes.
        (() => {
            const originalFetch = window.fetch.bind(window);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const safeMethods = new Set(['GET', 'HEAD', 'OPTIONS']);

            window.fetch = (input, init = {}) => {
                const requestUrl = typeof input === 'string' ? input : input.url;
                const method = (init.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
                const url = new URL(requestUrl, window.location.origin);

                if (csrfToken && url.origin === window.location.origin && !safeMethods.has(method)) {
                    const headers = new Headers(input instanceof Request ? input.headers : undefined);
                    new Headers(init.headers || {}).forEach((value, key) => headers.set(key, value));

                    if (!headers.has('X-CSRF-TOKEN')) {
                        headers.set('X-CSRF-TOKEN', csrfToken);
                    }

                    init = {...init, headers};
                }

                return originalFetch(input, init);
            };
        })();
    </script>

    @yield('script_data')

    <!--SCRIPT LIVEWIRE-->
    @livewireScripts
</body>

</html>
