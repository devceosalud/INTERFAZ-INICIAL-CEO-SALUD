@extends('layouts.app')


@section('css_data')
    <!-- Datatable -->
    <link href="{{ asset('assets/vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
    <!-- Custom Stylesheet -->
    <link href="{{ asset('assets/vendor/bootstrap-select/dist/css/bootstrap-select.min.css') }}" rel="stylesheet">

    {{-- <link href="{{ asset('assets/vendor/fullcalendar/css/main.min.css') }}" rel="stylesheet"> --}}

    <!-- STYLESHEETS CALENDAR-->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@7.0.1/skeleton.css' rel='stylesheet' />
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@7.0.1/themes/monarch/theme.css' rel='stylesheet' />
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@7.0.1/themes/monarch/palettes/purple.css' rel='stylesheet' />

    <link href="{{ asset('css/tesseract.css') }}" rel="stylesheet">
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

                <!-- FILTRO CALENDARIO -->
                <x-utils.available-schedule :specialties="$specialties" />
                <!-- FILTRO CALENDARIO -->

            </div>

        </div>
        <!--**********************************Content body end***********************************-->

        <!--**********************************Scripts***********************************-->
    @section('script_data')
        <!-- Required vendors -->
        <script src="{{ asset('assets/vendor/global/global.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>

        <script src="{{ asset('assets/vendor/moment/moment.min.js') }}"></script>
        {{-- <script src="{{ asset('assets/vendor/fullcalendar/js/main.min.js') }}"></script>
            <script src="{{ asset('assets/js/plugins-init/fullcalendar-init.js') }}"></script> --}}

        <!-- STANDARD JS -->
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@7.0.1/all/global.js"></script>
        <!-- THEME JS -->
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@7.0.1/themes/monarch/global.js"></script>

        <!-- Datatable -->
        <script src="{{ asset('assets/vendor/datatables/js/jquery.dataTables.min.js') }}"></script>
        <script src="{{ asset('assets/js/plugins-init/datatables.init.js') }}"></script>
        <script src="{{ asset('assets/js/custom.min.js') }}"></script>
        <script src="{{ asset('assets/js/deznav-init.js') }}"></script>

        <script src="{{ asset('js/admissionist/available-schedule/available-schedule.js') }}"></script>

    @endsection


    <!--**********************************Footer start***********************************-->
    @include('templates.footer')
    <!--**********************************Footer end***********************************-->

    <!--**********************************Support ticket button start***********************************-->

    <!--**********************************Support ticket button end***********************************-->


</div>
<!--**********************************Main wrapper end***********************************-->

@endsection
