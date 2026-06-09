@extends('layouts.app')


@section('body')
<!--*******************
                                    Preloader start
                                ********************-->
@include('templates.preloader')
<!--*******************
                                    Preloader end
                                ********************-->

<!--**********************************
                                    Main wrapper start
                                ***********************************-->
<div id="main-wrapper">

    <!--**********************************
                                        Nav header start
                                    ***********************************-->
    @include('templates.nav-header')
    <!--**********************************
                                        Nav header end
                                    ***********************************-->

    <!--**********************************
                                        Chat box start
                                    ***********************************-->
    @include('templates.chat-box')
    <!--**********************************
                                        Chat box End
                                    ***********************************-->

    <!--**********************************
                                        Header start
                                    ***********************************-->
    @include('templates.header')
    <!--**********************************
                                        Header end ti-comment-alt
                                    ***********************************-->

    <!--**********************************
                                        Sidebar start
                                    ***********************************-->
    @include('templates.sidebar')
    <!--**********************************
                                        Sidebar end
                                    ***********************************-->


    <!--**********************************
            Content body start
        ***********************************-->
    <div class="content-body">
        <!-- row -->
        <div class="container-fluid">
            <div class="form-head d-flex mb-3 mb-md-4 justify-content-between w-100">
                <div>
                    <a href="javascript:void(0);" class="btn btn-primary btn-rounded add-appointment"
                        data-bs-toggle="modal" data-bs-target="#exampleModal">+ Agregar Cita</a>
                </div>
                <div class="input-group search-area d-inline-flex">
                    <input type="text" class="form-control" placeholder="Search here">
                    <div class="input-group-append">
                        <button type="button" class="input-group-text"><i
                                class="flaticon-381-search-2"></i></button>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-xl-12">
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table id="example5"
                                    class="table table-striped patient-list mb-4 dataTablesCard fs-14">
                                    <thead>
                                        <tr>
                                            <th>Paciente</th>
                                            <th>Medico</th>
                                            <th>Especilidad</th>
                                            <th>Canal</th>
                                            <th>Hora</th>
                                            <th>Celular</th>
                                            <th>Pago</th>
                                            <th>Servicios</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="patient-info ps-0">
                                                <span>
                                                    <img src="images/avatar/1.jpg" alt="">
                                                </span>
                                                <span class="text-nowrap ms-2">Matthew</span>
                                            </td>
                                            <td class="text-primary">DR QUIROZ</td>
                                            <td>TRAUMATOLOGIA</td>
                                            <td>CEO SALUD</td>
                                            <td>2026/06/08 13:00</td>
                                            <td class="text-primary">9876512345</td>
                                            <td><button class="btn btn-danger">PENDIENTE</button></td>
                                            <td>CURACIONES</td>
                                            <td>
                                                <span class="me-3">
                                                    <a href="javascript:void(0);" class="edit-appointment"><i
                                                            class="fa fa-pencil fs-18 " aria-hidden="true"></i></a>
                                                </span>
                                                <span>
                                                    <i class="fa fa-trash fs-18 text-danger" aria-hidden="true"></i>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>

                                            <td class="patient-info ps-0">
                                                <span>
                                                    <img src="images/avatar/2.jpg" alt="">
                                                </span>
                                                <span class="text-nowrap ms-2">JOHN D RANDOLPH</span>
                                            </td>
                                            <td class="text-primary">DR QUIROZ</td>
                                            <td>TRAUMATOLOGIA</td>
                                            <td>CEO SALUD</td>
                                            <td>2026/06/08 13:00</td>
                                            <td class="text-primary">9876512345</td>
                                            <td><button class="btn btn-danger">PENDIENTE</button></td>
                                            <td>CURACIONES</td>
                                            <td>
                                                <span class="me-3">
                                                    <a href="javascript:void(0);" class="edit-appointment"><i
                                                            class="fa fa-pencil fs-18 " aria-hidden="true"></i></a>
                                                </span>
                                                <span>
                                                    <i class="fa fa-trash fs-18 text-danger" aria-hidden="true"></i>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>

                                            <td class="patient-info ps-0">
                                                <span>
                                                    <img src="images/avatar/3.jpg" alt="">
                                                </span>
                                                <span class="text-nowrap ms-2">JOHN D RANDOLPH</span>
                                            </td>
                                            <td class="text-primary">DR QUIROZ</td>
                                            <td>TRAUMATOLOGIA</td>
                                            <td>CEO SALUD</td>
                                            <td>2026/06/08 13:00</td>
                                            <td class="text-primary">9876512345</td>
                                            <td><button class="btn btn-primary">PAGADO</button></td>
                                            <td>MFR</td>
                                            <td>
                                                <span class="me-3">
                                                    <a href="javascript:void(0);" class="edit-appointment"><i
                                                            class="fa fa-pencil fs-18 " aria-hidden="true"></i></a>
                                                </span>
                                                <span>
                                                    <i class="fa fa-trash fs-18 text-danger" aria-hidden="true"></i>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>

                                            <td class="patient-info ps-0">
                                                <span>
                                                    <img src="images/avatar/4.jpg" alt="">
                                                </span>
                                                <span class="text-nowrap ms-2">Asher Anthony</span>
                                            </td>
                                            <td class="text-primary">DR QUIROZ</td>
                                            <td>TRAUMATOLOGIA</td>
                                            <td>CEO SALUD</td>
                                            <td>2026/06/08 13:00</td>
                                            <td class="text-primary">9876512345</td>
                                            <td><button class="btn btn-danger">PENDIENTE</button></td>
                                            <td>TRAUMATOLOGIA</td>
                                            <td>
                                                <span class="me-3">
                                                    <a href="javascript:void(0);" class="edit-appointment"><i
                                                            class="fa fa-pencil fs-18 " aria-hidden="true"></i></a>
                                                </span>
                                                <span>
                                                    <i class="fa fa-trash fs-18 text-danger" aria-hidden="true"></i>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>

                                            <td class="patient-info ps-0">
                                                <span>
                                                    <img src="images/avatar/6.jpg" alt="">
                                                </span>
                                                <span class="text-nowrap ms-2">Carter Anthony</span>
                                            </td>
                                            <td class="text-primary">DR QUIROZ</td>
                                            <td>TRAUMATOLOGIA</td>
                                            <td>CEO SALUD</td>
                                            <td>2026/06/08 13:00</td>
                                            <td class="text-primary">9876512345</td>
                                            <td><button class="btn btn-danger">PENDIENTE</button></td>
                                            <td>MFR</td>
                                            <td>
                                                <span class="me-3">
                                                    <a href="javascript:void(0);" class="edit-appointment"><i
                                                            class="fa fa-pencil fs-18 " aria-hidden="true"></i></a>
                                                </span>
                                                <span>
                                                    <i class="fa fa-trash fs-18 text-danger" aria-hidden="true"></i>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>

                                            <td class="patient-info ps-0">
                                                <span>
                                                    <img src="images/avatar/4.jpg" alt="">
                                                </span>
                                                <span class="text-nowrap ms-2">David Anthony</span>
                                            </td>
                                            <td class="text-primary">DR QUIROZ</td>
                                            <td>TRAUMATOLOGIA</td>
                                            <td>CEO SALUD</td>
                                            <td>2026/06/08 13:00</td>
                                            <td class="text-primary">9876512345</td>
                                            <td><button class="btn btn-warning">PARCIAL</button></td>
                                            <td>CURACIONES</td>
                                            <td>
                                                <span class="me-3">
                                                    <a href="javascript:void(0);" class="edit-appointment"><i
                                                            class="fa fa-pencil fs-18 " aria-hidden="true"></i></a>
                                                </span>
                                                <span>
                                                    <i class="fa fa-trash fs-18 text-danger" aria-hidden="true"></i>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>

                                            <td class="patient-info ps-0">
                                                <span>
                                                    <img src="images/avatar/6.jpg" alt="">
                                                </span>
                                                <span class="text-nowrap ms-2">Anthony David</span>
                                            </td>
                                            <td class="text-primary">DR QUIROZ</td>
                                            <td>TRAUMATOLOGIA</td>
                                            <td>CEO SALUD</td>
                                            <td>2026/06/08 13:00</td>
                                            <td class="text-primary">9876512345</td>
                                            <td><button class="btn btn-warning">PARCIAL</button></td>
                                            <td>MFR</td>
                                            <td>
                                                <span class="me-3">
                                                    <a href="javascript:void(0);" class="edit-appointment"><i
                                                            class="fa fa-pencil fs-18 " aria-hidden="true"></i></a>
                                                </span>
                                                <span>
                                                    <i class="fa fa-trash fs-18 text-danger" aria-hidden="true"></i>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>

                                            <td class="patient-info ps-0">
                                                <span>
                                                    <img src="images/avatar/1.jpg" alt="">
                                                </span>
                                                <span class="text-nowrap ms-2">Matthew</span>
                                            </td>
                                            <td class="text-primary">DR QUIROZ</td>
                                            <td>TRAUMATOLOGIA</td>
                                            <td>CEO SALUD</td>
                                            <td>2026/06/08 13:00</td>
                                            <td class="text-primary">9876512345</td>
                                            <td><button class="btn btn-warning">PARCIAL</button></td>
                                            <td>CURACIONES</td>
                                            <td>
                                                <span class="me-3">
                                                    <a href="javascript:void(0);" class="edit-appointment"><i
                                                            class="fa fa-pencil fs-18 " aria-hidden="true"></i></a>
                                                </span>
                                                <span>
                                                    <i class="fa fa-trash fs-18 text-danger" aria-hidden="true"></i>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>

                                            <td class="patient-info ps-0">
                                                <span>
                                                    <img src="images/avatar/2.jpg" alt="">
                                                </span>
                                                <span class="text-nowrap ms-2">JOHN D RANDOLPH</span>
                                            </td>
                                            <td class="text-primary">DR QUIROZ</td>
                                            <td>TRAUMATOLOGIA</td>
                                            <td>CEO SALUD</td>
                                            <td>2026/06/08 13:00</td>
                                            <td class="text-primary">9876512345</td>
                                            <td><button class="btn btn-danger">PENDIENTE</button></td>
                                            <td>CURACIONES</td>
                                            <td>
                                                <span class="me-3">
                                                    <a href="javascript:void(0);" class="edit-appointment"><i
                                                            class="fa fa-pencil fs-18 " aria-hidden="true"></i></a>
                                                </span>
                                                <span>
                                                    <i class="fa fa-trash fs-18 text-danger" aria-hidden="true"></i>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>

                                            <td class="patient-info ps-0">
                                                <span>
                                                    <img src="images/avatar/3.jpg" alt="">
                                                </span>
                                                <span class="text-nowrap ms-2">JOHN D RANDOLPH</span>
                                            </td>
                                            <td class="text-primary">DR QUIROZ</td>
                                            <td>TRAUMATOLOGIA</td>
                                            <td>CEO SALUD</td>
                                            <td>2026/06/08 13:00</td>
                                            <td class="text-primary">9876512345</td>
                                            <td><button class="btn btn-primary">PAGADO</button></td>
                                            <td>MFR</td>
                                            <td>
                                                <span class="me-3">
                                                    <a href="javascript:void(0);" class="edit-appointment"><i
                                                            class="fa fa-pencil fs-18 " aria-hidden="true"></i></a>
                                                </span>
                                                <span>
                                                    <i class="fa fa-trash fs-18 text-danger" aria-hidden="true"></i>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>

                                            <td class="patient-info ps-0">
                                                <span>
                                                    <img src="images/avatar/4.jpg" alt="">
                                                </span>
                                                <span class="text-nowrap ms-2">Asher Anthony</span>
                                            </td>
                                            <td class="text-primary">DR QUIROZ</td>
                                            <td>TRAUMATOLOGIA</td>
                                            <td>CEO SALUD</td>
                                            <td>2026/06/08 13:00</td>
                                            <td class="text-primary">9876512345</td>
                                            <td><button class="btn btn-primary">PAGADO</button></td>
                                            <td>TRAUMATOLOGIA</td>
                                            <td>
                                                <span class="me-3">
                                                    <a href="javascript:void(0);" class="edit-appointment"><i
                                                            class="fa fa-pencil fs-18 " aria-hidden="true"></i></a>
                                                </span>
                                                <span>
                                                    <i class="fa fa-trash fs-18 text-danger" aria-hidden="true"></i>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>

                                            <td class="patient-info ps-0">
                                                <span>
                                                    <img src="images/avatar/6.jpg" alt="">
                                                </span>
                                                <span class="text-nowrap ms-2">Carter Anthony</span>
                                            </td>
                                            <td class="text-primary">DR QUIROZ</td>
                                            <td>TRAUMATOLOGIA</td>
                                            <td>CEO SALUD</td>
                                            <td>2026/06/08 13:00</td>
                                            <td class="text-primary">9876512345</td>
                                            <td><button class="btn btn-primary">PAGADO</button></td>
                                            <td>MFR</td>
                                            <td>
                                                <span class="me-3">
                                                    <a href="javascript:void(0);" class="edit-appointment"><i
                                                            class="fa fa-pencil fs-18 " aria-hidden="true"></i></a>
                                                </span>
                                                <span>
                                                    <i class="fa fa-trash fs-18 text-danger" aria-hidden="true"></i>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>

                                            <td class="patient-info ps-0">
                                                <span>
                                                    <img src="images/avatar/4.jpg" alt="">
                                                </span>
                                                <span class="text-nowrap ms-2">David Anthony</span>
                                            </td>
                                            <td class="text-primary">DR QUIROZ</td>
                                            <td>TRAUMATOLOGIA</td>
                                            <td>CEO SALUD</td>
                                            <td>2026/06/08 13:00</td>
                                            <td class="text-primary">9876512345</td>
                                            <td><button class="btn btn-primary">PAGADO</button></td>
                                            <td>CURACIONES</td>
                                            <td>
                                                <span class="me-3">
                                                    <a href="javascript:void(0);" class="edit-appointment"><i
                                                            class="fa fa-pencil fs-18 " aria-hidden="true"></i></a>
                                                </span>
                                                <span>
                                                    <i class="fa fa-trash fs-18 text-danger" aria-hidden="true"></i>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>

                                            <td class="patient-info ps-0">
                                                <span>
                                                    <img src="images/avatar/6.jpg" alt="">
                                                </span>
                                                <span class="text-nowrap ms-2">Anthony David</span>
                                            </td>
                                            <td class="text-primary">DR QUIROZ</td>
                                            <td>TRAUMATOLOGIA</td>
                                            <td>CEO SALUD</td>
                                            <td>2026/06/08 13:00</td>
                                            <td class="text-primary">9876512345</td>
                                            <td><button class="btn btn-warning">PARCIAL</button></td>
                                            <td>MFR</td>
                                            <td>
                                                <span class="me-3">
                                                    <a href="javascript:void(0);" class="edit-appointment"><i
                                                            class="fa fa-pencil fs-18 " aria-hidden="true"></i></a>
                                                </span>
                                                <span>
                                                    <i class="fa fa-trash fs-18 text-danger" aria-hidden="true"></i>
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Agregar nueva Cita:</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="form-cita">
                            <div class="row">

                                <!-- ================= PACIENTE ================= -->
                                <div class="col-12">
                                    <h5 class="mb-3">Datos del Paciente</h5>
                                </div>

                                <div class="col-xl-4">
                                    <div class="form-group">
                                        <label>Buscar Paciente</label>
                                        <!--
											<select class="form-control select2" name="paciente_id" id="paciente_id">
												<option value="">Buscar por HC, DNI</option>
											</select>
											-->

                                        <input type="text" class="form-control" id="paciente_id">
                                    </div>
                                </div>

                                <div class="col-xl-8">
                                    <div class="form-group">
                                        <label>Paciente seleccionado</label>
                                        <input type="text" class="form-control" id="paciente_nombre" readonly>
                                    </div>
                                </div>

                                <!-- ================= PROGRAMACIÓN ================= -->
                                <div class="col-12 mt-4">
                                    <h5 class="mb-3">Programación de la Cita</h5>
                                </div>

                                <div class="col-xl-4">
                                    <div class="form-group">
                                        <label>Especialidad</label>
                                        <select class="form-control" name="especialidad_id" id="especialidad_id">
                                            <option value="">Seleccione</option>
                                            <option value="">TRAUMATOLOGIA</option>
                                            <option value="">MEDICINA FÍSICA Y REHABILITACIÓN</option>
                                            <option value="">READIOLOGIA</option>
                                            <option value="">NEUROLOGÍA</option>
                                            <option value="">REUMATOLOGIA</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-xl-4">
                                    <div class="form-group">
                                        <label>Médico</label>
                                        <select class="form-control" name="medico_id" id="medico_id">
                                            <option value="">Seleccionar</option>
                                            <option value="">DR QUIROZ</option>
                                            <option value="">DR MENOZA</option>
                                            <option value="">DR TORRES</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-xl-4">
                                    <div class="form-group">
                                        <label>Servicio</label>
                                        <select class="form-control" name="servicio_id" id="servicio_id">
                                            <option value="">Seleccione</option>
                                            <option value="1">CONSULTA TRAUMATOLOGIA</option>
                                            <option value="2">CONSULTA MFR</option>
                                            <option value="3">CONSULTA REUMATOLOGIA</option>
                                            <option value="4">CURACIONES</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- ================= FECHA Y HORA ================= -->
                                <div class="col-xl-6">
                                    <div class="form-group">
                                        <label>Fecha</label>
                                        <input type="date" class="form-control" name="fecha">
                                    </div>
                                </div>

                                <div class="col-xl-6">
                                    <div class="form-group">
                                        <label>Hora</label>
                                        <input type="text" class="form-control" name="hora">
                                    </div>
                                </div>

                                <!-- ================= ORIGEN ================= -->
                                <div class="col-12 mt-4">
                                    <h5 class="mb-3">Origen de la Cita</h5>
                                </div>

                                <div class="col-xl-6">
                                    <div class="form-group">
                                        <label>Canal</label>
                                        <select class="form-control" name="canal_id">
                                            <option value="">Seleccione</option>
                                            <option value="CEO_SALUD">CEO SALUD</option>
                                            <option value="DR_QUIROZ">DR QUIROZ</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-xl-6">
                                    <div class="form-group">
                                        <label>Medio de Interacción</label>
                                        <select class="form-control" name="medio_interaccion_id">
                                            <option value="">Seleccione</option>
                                            <option value="1">CONTINUO</option>
                                            <option value="2">RECOMENDADO</option>
                                            <option value="3">FACEBOOK</option>
                                            <option value="">TIK TOK</option>
                                            <option value="3">REDES SOCIALES CEO</option>
                                            <option value="4">REDES SOCIALES DR. QUIROZ</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- ================= DETALLES ================= -->
                                <div class="col-12 mt-4">
                                    <h5 class="mb-3">Detalles</h5>
                                </div>

                                <div class="col-xl-12">
                                    <div class="form-group">
                                        <label>Motivo de Consulta</label>
                                        <textarea class="form-control" name="motivo_consulta" rows="3"></textarea>
                                    </div>
                                </div>

                                <!-- ================= PAGO ================= -->
                                <div class="col-12 mt-4">
                                    <h5 class="mb-3">Información de Pago</h5>
                                </div>

                                <div class="col-xl-3">
                                    <div class="form-group">
                                        <label>Precio Programado</label>
                                        <input type="text" class="form-control" id="precio_programado" readonly>
                                    </div>
                                </div>

                                <div class="col-xl-3">
                                    <div class="form-group">
                                        <label>Total Pagado</label>
                                        <input type="number" class="form-control" name="total_pagado"
                                            id="total_pagado" value="0">
                                    </div>
                                </div>

                                <div class="col-xl-3">
                                    <div class="form-group">
                                        <label>Saldo Pendiente</label>
                                        <input type="text" class="form-control" id="saldo_pendiente">
                                    </div>
                                </div>

                                <div class="col-xl-3">
                                    <div class="form-group">
                                        <label>Estado Pago</label>
                                        <select class="form-control" name="estado_pago">
                                            <option value="">Seleccione</option>
                                            <option value="">PENDIENTE</option>
                                            <option value="">PARCIAL</option>
                                            <option value="">PAGADO</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- ================= OBSERVACIONES ================= -->
                                <div class="col-12 mt-3">
                                    <div class="form-group">
                                        <label>Observaciones</label>
                                        <textarea class="form-control" name="observaciones" rows="2"></textarea>
                                    </div>
                                </div>

                                <!-- ================= BOTÓN ================= -->
                                <div class="col-12 text-end mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        Registrar Cita
                                    </button>
                                </div>

                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger light" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--**********************************
            Content body end
        ***********************************-->


    <!--**********************************
                                        Footer start
                                    ***********************************-->
    @include('templates.footer')
    <!--**********************************
                                        Footer end
                                    ***********************************-->

    <!--**********************************
                                       Support ticket button start
                                    ***********************************-->

    <!--**********************************
                                       Support ticket button end
                                    ***********************************-->


</div>
<!--**********************************
                                    Main wrapper end
                                ***********************************-->


@endsection