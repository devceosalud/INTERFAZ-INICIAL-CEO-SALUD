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
    </style>
    <!--ESTILOS LIVEWIRE"-->
    @livewireStyles
</head>

<body>

    @yield('body')


    @yield('script_data')

    <!--SCRIPT LIVEWIRE-->
    @livewireScripts
</body>

</html>
