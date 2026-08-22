@extends('layouts.app')



@section('css_data')
    <!-- Datatable -->
    <link href="{{ asset('assets/vendor/datatables/css/jquery.dataTables.min.css') }}" rel="stylesheet">
    <!-- Custom Stylesheet -->
    <link href="{{ asset('assets/vendor/bootstrap-select/dist/css/bootstrap-select.min.css') }}" rel="stylesheet">

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
        <style>
            .oculto_card_responsable {
                display: none;
            }
        </style>

        <div class="content-body">
            <div class="container-fluid">

                {{-- LISTA DE PACIENTES --}}
                <x-utils.patients :patients="$patients" />
                {{-- LISTA DE PACIENTES --}}
            </div>

            @include('admissionist.patient.crud.create')

            @include('admissionist.patient.crud.edit')

            @include('admissionist.patient.modal.modal-agendar')

            @include('admissionist.patient.modal.appointment')
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

        <!-- TESSERACT -->
        <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>


        <script src="{{ asset('js/admissionist/patient/patient.js') }}"></script>
        <script src="{{ asset('js/admissionist/tesseract/tesseract.js') }}"></script>
        <script src="{{ asset('js/admissionist/appointment/appointment.js') }}"></script>
    @endsection



    <!--**********************************Footer start***********************************-->
    @include('templates.footer')
    <!--**********************************Footer end***********************************-->

    <!--**********************************Support ticket button start***********************************-->

    <!--**********************************Support ticket button end***********************************-->


</div>
<!--**********************************Main wrapper end***********************************-->


@endsection
