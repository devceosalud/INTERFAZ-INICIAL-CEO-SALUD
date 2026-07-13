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
                <div class="form-head d-flex mb-3 mb-md-4 align-items-start">
                    <div class="me-auto d-none d-lg-block">
                        <h3 class="text-black font-w600">Bienvenido a CEOSALUD!</h3>
                        <p class="mb-0 fs-18">Panel Administrativo</p>
                    </div>

                    <div class="input-group search-area ms-auto d-inline-flex">
                        <input type="text" class="form-control" placeholder="Palabra Clave">
                        <div class="input-group-append">
                            <button type="button" class="input-group-text"><i class="flaticon-381-search-2"></i></button>
                        </div>
                    </div>
                </div>
                <div class="row">

        
                    @if ($role->contains('ADMISION'))
                        <div class="col-xl-6 col-xxl-6 col-sm-6">
                            <a href="{{ route('admissionit.patient.index') }}" class="text-decoration-none">
                                <div class="card gradient-bx text-white bg-dark">
                                    <div class="card-body">
                                        <div class="media align-items-center">

                                            <div class="media-body">
                                                <p class="mb-1 fw-bold">
                                                    Admisión Pacientes
                                                </p>

                                                <small class="text-white">
                                                    Registrar y gestionar pacientes
                                                </small>
                                            </div>

                                            <span class="border rounded-circle p-4">
                                                <svg width="34" height="34" viewBox="0 0 24 24" fill="none"
                                                    xmlns="http://www.w3.org/2000/svg">

                                                    <path
                                                        d="M12 12C14.2091 12 16 10.2091 16 8C16 5.79086 14.2091 4 12 4C9.79086 4 8 5.79086 8 8C8 10.2091 9.79086 12 12 12Z"
                                                        fill="white" />

                                                    <path
                                                        d="M12 14C8.68629 14 6 16.6863 6 20H18C18 16.6863 15.3137 14 12 14Z"
                                                        fill="white" />

                                                    <path
                                                        d="M5 11C6.65685 11 8 9.65685 8 8C8 6.34315 6.65685 5 5 5C3.34315 5 2 6.34315 2 8C2 9.65685 3.34315 11 5 11Z"
                                                        fill="white" opacity="0.7" />

                                                    <path
                                                        d="M1 20C1 17.7909 2.79086 16 5 16C6.10457 16 7.10457 16.4477 7.82843 17.1716C6.6997 17.8825 5.86447 19.0151 5.5 20H1Z"
                                                        fill="white" opacity="0.7" />

                                                    <path
                                                        d="M19 11C20.6569 11 22 9.65685 22 8C22 6.34315 20.6569 5 19 5C17.3431 5 16 6.34315 16 8C16 9.65685 17.3431 11 19 11Z"
                                                        fill="white" opacity="0.7" />

                                                    <path
                                                        d="M23 20C23 17.7909 21.2091 16 19 16C17.8954 16 16.8954 16.4477 16.1716 17.1716C17.3003 17.8825 18.1355 19.0151 18.5 20H23Z"
                                                        fill="white" opacity="0.7" />
                                                </svg>
                                            </span>

                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <div class="col-xl-6 col-xxl-6 col-sm-6">
                            <a href="{{ route('admissionit.appointment.index') }}" class="text-decoration-none">
                                <div class="card gradient-bx text-white bg-info">
                                    <div class="card-body">
                                        <div class="media align-items-center">

                                            <div class="media-body">
                                                <p class="mb-1 fw-bold">
                                                    Admisión Citas
                                                </p>

                                                <small class="text-white">
                                                    Programar y gestionar citas médicas
                                                </small>
                                            </div>

                                            <span class="border rounded-circle p-4">
                                                <svg width="34" height="34" viewBox="0 0 38 38" fill="none"
                                                    xmlns="http://www.w3.org/2000/svg">
                                                    <path
                                                        d="M37.3333 15.6666C37.3383 14.7488 37.0906 13.8473 36.6174 13.061C36.1441 12.2746 35.4635 11.6336 34.6501 11.2084C33.8368 10.7831 32.9221 10.5899 32.0062 10.65C31.0904 10.7101 30.2087 11.021 29.4579 11.5489C28.707 12.0767 28.1159 12.8011 27.7494 13.6425C27.3829 14.484 27.255 15.4101 27.3799 16.3194C27.5047 17.2287 27.8774 18.086 28.4572 18.7976C29.0369 19.5091 29.8013 20.0473 30.6667 20.3533V25.6666C30.6667 27.8768 29.7887 29.9964 28.2259 31.5592C26.6631 33.122 24.5435 34 22.3333 34C20.1232 34 18.0036 33.122 16.4408 31.5592C14.878 29.9964 14 27.8768 14 25.6666V23.8666C16.7735 23.4642 19.3097 22.0777 21.1456 19.9603C22.9815 17.8429 23.9946 15.1358 24 12.3333V2.33329C24 1.89127 23.8244 1.46734 23.5118 1.15478C23.1993 0.842221 22.7754 0.666626 22.3333 0.666626H17.3333C16.8913 0.666626 16.4674 0.842221 16.1548 1.15478C15.8423 1.46734 15.6667 1.89127 15.6667 2.33329C15.6667 2.77532 15.8423 3.19924 16.1548 3.5118C16.4674 3.82436 16.8913 3.99996 17.3333 3.99996H20.6667V12.3333C20.6667 14.5434 19.7887 16.663 18.2259 18.2258C16.6631 19.7887 14.5435 20.6666 12.3333 20.6666C10.1232 20.6666 8.00358 19.7887 6.44077 18.2258C4.87797 16.663 4 14.5434 4 12.3333V3.99996H7.33333C7.77536 3.99996 8.19928 3.82436 8.51184 3.5118C8.8244 3.19924 9 2.77532 9 2.33329C9 1.89127 8.8244 1.46734 8.51184 1.15478C8.19928 0.842221 7.77536 0.666626 7.33333 0.666626H2.33333C1.8913 0.666626 1.46738 0.842221 1.15482 1.15478C0.842259 1.46734 0.666664 1.89127 0.666664 2.33329V12.3333C0.672024 15.1358 1.68515 17.8429 3.52106 19.9603C5.35697 22.0777 7.8932 23.4642 10.6667 23.8666V25.6666C10.6667 28.7608 11.8958 31.7283 14.0837 33.9162C16.2717 36.1041 19.2391 37.3333 22.3333 37.3333C25.4275 37.3333 28.395 36.1041 30.5829 33.9162C32.7708 31.7283 34 28.7608 34 25.6666V20.3533C34.9723 20.0131 35.8151 19.3797 36.4122 18.5402C37.0092 17.7008 37.3311 16.6967 37.3333 15.6666Z"
                                                        fill="white" />
                                                </svg>
                                            </span>

                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endif

                    @if ($role->contains('ADMINISTRADOR'))
                        <div class="col-xl-3 col-xxl-4 col-lg-4">
                            <div class="card">
                                <div class="card-header border-0 pb-0">
                                    <h3 class="fs-20 mb-0 text-black">Atendidos</h3>
                                </div>
                                <div class="card-body">
                                    <div>
                                        <span class="text-info fs-26 font-w600 me-3">1800</span>
                                        <span class="text-secondary fs-18 font-w400">1980</span>
                                    </div>
                                    <div id="line-chart"></div>
                                </div>
                            </div>
                        </div>


                        <div class="col-xl-9 col-xxl-8 col-lg-8">
                            <div class="card">
                                <div class="card-header d-sm-flex d-block border-0 pb-0">
                                    <h3 class="fs-20 mb-3 mb-sm-0 text-black">Pacientes Por Mes</h3>
                                </div>
                                <div class="card-body">
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="monthly">
                                            <div id="chartBar"></div>
                                        </div>
                                        <div class="tab-pane fade" id="weekly">
                                            <div id="chartBar1"></div>
                                        </div>
                                        <div class="tab-pane fade" id="today">
                                            <div id="chartBar2"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($role->contains('RECEPCION'))
                        <div class="col-xl-12 col-xxl-12 col-lg-12">
                            <div class="card border-0 pb-0">
                                <div class="card-header flex-wrap border-0 pb-0">
                                    <h3 class="fs-20 mb-0 text-black">Pacientes Recientes</h3>
                                    <!--<a href="patient-list.html" class="text-primary font-w500">View more >></a> -->
                                </div>
                                <div class="card-body recent-patient px-0">
                                    <div id="DZ_W_Todo2" class="widget-media px-4 dz-scroll height320">
                                        <ul class="timeline">
                                            <li>
                                                <div class="timeline-panel flex-wrap">
                                                    <div class="media-body">
                                                        <h5 class="mb-1"><a class='text-black'
                                                                href='/patient-details'>JUan
                                                                Arevalo</a></h5>
                                                        <span class="fs-14">24 años</span>
                                                    </div>
                                                    <a href="javascript:void(0);" class="text-warning mt-2">Pendiente</a>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="timeline-panel flex-wrap">
                                                    <div class="media-body">
                                                        <h5 class="mb-1"><a class='text-black'
                                                                href='/patient-details'>Hillary Rivera</a></h5>
                                                        <span class="fs-14">22 años</span>
                                                    </div>
                                                    <a href="javascript:void(0);" class="text-info mt-2">Atendido</a>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="timeline-panel flex-wrap">
                                                    <div class="media-body">
                                                        <h5 class="mb-1"><a class='text-black'
                                                                href='/patient-details'>Omar
                                                                Meneses</a></h5>
                                                        <span class="fs-14">44 años</span>
                                                    </div>
                                                    <a href="javascript:void(0);" class="text-danger mt-2">Cancelado</a>
                                                </div>
                                            </li>
                                            <li>
                                                <div class="timeline-panel flex-wrap">
                                                    <div class="media-body">
                                                        <h5 class="mb-1"><a class='text-black'
                                                                href='/patient-details'>Julia Porras</a></h5>
                                                        <span class="fs-14">55 años</span>
                                                    </div>
                                                    <a href="javascript:void(0);" class="text-primary mt-2">No asistio</a>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                </div>
            </div>
        </div>
        <!--**********************************Content body end***********************************-->


        <!--**********************************Scripts***********************************-->
        <!-- Required vendors -->
        <script src="{{ asset('assets/vendor/global/global.min.js') }}"></script>

        <script src="{{ asset('assets/vendor/chart.js/Chart.bundle.min.js') }}"></script>
        <script src="{{ asset('assets/vendor/owl-carousel/owl.carousel.js') }}"></script>

        <!-- Apex Chart -->
        <script src="{{ asset('assets/vendor/apexchart/apexchart.js') }}"></script>

        <!-- Dashboard 1 -->
        <script src="{{ asset('assets/js/dashboard/dashboard-1.js') }}"></script>
        <script src="{{ asset('assets/js/custom.min.js') }}"></script>
        <script src="{{ asset('assets/js/deznav-init.js') }}"></script>



        <!--**********************************Footer start***********************************-->
        @include('templates.footer')
        <!--**********************************Footer end***********************************-->

        <!--**********************************Support ticket button start***********************************-->

        <!--**********************************Support ticket button end***********************************-->


    </div>
    <!--**********************************Main wrapper end***********************************-->
@endsection
