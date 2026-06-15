@extends('layouts.app')


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
            <!-- row -->
            <div class="container-fluid">
                <div class="form-head d-flex mb-3 mb-md-4 justify-content-between w-100">
                    <div>
                        <a href="javascript:void(0);" class="btn btn-primary btn-rounded add-appointment"
                            data-bs-toggle="modal" data-bs-target="#specialtytModalCreate">+ Agregar Especialidad</a>
                    </div>
                </div>
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table id="datatable"
                                        class="table table-striped patient-list mb-4 dataTablesCard fs-14">
                                        <thead>
                                            <tr>
                                                <th>Código</th>
                                                <th>Especialidad</th>
                                                <th class="text-end">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($specialties as $specialty)
                                                <tr>
                                                    <td class="patient-info ps-0">
                                                        <span class="text-nowrap ms-2"> {{ $specialty->id }}
                                                        </span>
                                                    </td>
                                                    <td class="text-primary"> {{ $specialty->nombre }} </td>

                                                    <td class="text-end">
                                                        <span class="me-3">
                                                            <a href="#" class="edit-patient"
                                                                data-id="{{ $specialty->id }}">
                                                                <i class="fa fa-pencil fs-18 text-success"></i>
                                                            </a>
                                                        </span>
                                                        <span>
                                                            <i class="fa fa-trash fs-18 text-danger"></i>
                                                        </span>
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

            @include('admin.master.specialty.crud.create')
        </div>
        <!--**********************************Content body end***********************************-->


        <!--**********************************Scripts***********************************-->
        <!-- Required vendors -->
    @section('script_data')
        <script src="{{ asset('assets/vendor/global/global.min.js') }}"></script>

        <!-- Dashboard 1 -->
        <script src="{{ asset('assets/js/custom.min.js') }}"></script>
        <script src="{{ asset('assets/js/deznav-init.js') }}"></script>
    @endsection


    <!--**********************************Footer start***********************************-->
    @include('templates.footer')
    <!--********************************** Footer end***********************************-->

    <!--**********************************Support ticket button start***********************************-->

    <!--**********************************Support ticket button end***********************************-->


</div>
<!--**********************************Main wrapper end***********************************-->

@endsection
