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
    <style>
        .oculto_card_responsable {
            display: none;
        }
    </style>
    <div class="content-body">
        <!-- row -->
        <div class="container-fluid">
            <div class="form-head d-flex mb-3 mb-md-4 justify-content-between w-100">
                <div class="">
                    <a href="javascript:void(0);" class="btn btn-primary btn-rounded add-appointment"
                        data-bs-toggle="modal" data-bs-target="#exampleModal">+ Agregar Paciente</a>
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
                                            <th>Nombre y Apellidos</th>
                                            <th>Nro.HC</th>
                                            <th>Tipo iden.</th>
                                            <th>Nro documento</th>
                                            <th>Sexo</th>
                                            <th>Edad</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="patient-info ps-0">
                                                <span>
                                                    <img src="images/avatar/2.jpg" alt="">
                                                </span>
                                                <span class="text-nowrap ms-2">Matthew Cordova</span>
                                            </td>
                                            <td class="text-primary">4890</td>
                                            <td class="text-primary">DNI</td>
                                            <td>67543456</td>
                                            <td><button class="btn btn-primary">HOMBRE</button></td>
                                            <td>45</td>
                                            <td class="text-end">
                                                <span class="me-3">
                                                    <a href="javascript:void(0);" class="edit-staff"><i
                                                            class="fa fa-pencil fs-18 text-success"></i></a>
                                                </span>
                                                <span>
                                                    <i class="fa fa-trash fs-18 text-danger"></i>
                                                </span>
                                            </td>
                                        </tr>

                                        <tr>
                                            <td class="patient-info ps-0">
                                                <span>
                                                    <img src="images/avatar/2.jpg" alt="">
                                                </span>
                                                <span class="text-nowrap ms-2">Gabriela Ladera</span>
                                            </td>
                                            <td class="text-primary">4891</td>
                                            <td class="text-primary">DNI</td>
                                            <td>87567545</td>
                                            <td><button class="btn btn-danger">MUJER</button></td>
                                            <td>45</td>
                                            <td class="text-end">
                                                <span class="me-3">
                                                    <a href="javascript:void(0);" class="edit-staff"><i
                                                            class="fa fa-pencil fs-18 text-success"></i></a>
                                                </span>
                                                <span>
                                                    <i class="fa fa-trash fs-18 text-danger"></i>
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
                        <h5 class="modal-title" id="exampleModalLabel">Paciente</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        </button>
                    </div>
                    <div class="modal-body">
                        <form>
                            <div class="row">
                                <!--
						<div class="col-xl-12">
							<div class="form-group">
								<label  class="col-form-label">Historia Clinica:</label>
								<input type="text" class="form-control" id="name1" placeholder="4544">
							 </div>
                         -->
                                <div class="col-xl-12">
                                    <div class="form-group">
                                        <div class="d-flex justify-content-between">
                                            <label class="col-form-label">Nombre:</label>

                                            <div>
                                                <input type="checkbox" id="responsable_id" name="responsable_id"
                                                    value="Bike">
                                                <label for="responsable_id"> Viene acompañado?</label><br>
                                            </div>
                                        </div>
                                        <input type="text" class="form-control" name="nombre_paciente"
                                            id="nombre_paciente" placeholder="Name">
                                    </div>
                                </div>

                                <div class="col-xl-4">
                                    <div class="form-group">
                                        <label class="col-form-label">Apellido Paterno:</label>
                                        <input type="text" class="form-control" name="apellido_paterno"
                                            id="apellido_paterno" placeholder="Last Name">
                                    </div>
                                </div>

                                <div class="col-xl-4">
                                    <div class="form-group">
                                        <label class="col-form-label">Apellido Materno:</label>
                                        <input type="text" class="form-control" name="apellido_materno"
                                            id="apellido_materno" placeholder="Last Name">
                                    </div>
                                </div>

                                <div class="col-xl-4">
                                    <div class="form-group">
                                        <label class="col-form-label">Genero:</label>
                                        <select class="form-control" name="genero_paciente" id="genero_paciente">
                                            <option>M</option>
                                            <option>F</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-xl-4">
                                    <div class="form-group">
                                        <label class="col-form-label">Tipo Identificación:</label>
                                        <select class="form-control" name="tipo_identificacion"
                                            id="tipo_identificacion">
                                            <option value="DNI">DNI</option>
                                            <option value="CARNET EXTRANJERI">CARNET EXTRANJERIA</option>
                                            <option value="PTP">PTP</option>
                                            <option value="TAM">TAM</option>
                                            <option value="RUC">RUC</option>
                                            <option value="PASAPORTE">PASAPORTE</option>
                                            <option value="SIN DOCUMENTOS">SIN DOCUMENTOS</option>
                                        </select>
                                    </div>
                                </div>


                                <div class="col-xl-4">
                                    <div class="form-group">
                                        <label class="col-form-label">Número de identidad:</label>
                                        <input size="16" type="number" name="numero_identidad" id="numero_identidad"
                                            class="form-control" placeholder="987654321">
                                    </div>
                                </div>

                                <div class="col-xl-4">
                                    <div class="form-group">
                                        <label class="col-form-label">Fecha de Nacimiento:</label>
                                        <input type="date" class="form-control" name="fecha_nacimiento"
                                            id="fecha_nacimiento" placeholder="987654321">
                                    </div>
                                </div>

                                <div class="col-xl-4">
                                    <div class="form-group">
                                        <label class="col-form-label">Ocupación:</label>
                                        <input type="text" class="form-control" name="ocupacion" id="ocupacion"
                                            placeholder="">
                                    </div>
                                </div>

                                <div class="col-xl-4">
                                    <div class="form-group">
                                        <label class="col-form-label">Grado de Instrucción</label>
                                        <input type="text" class="form-control" name="grado_instruccion"
                                            id="grado_instruccion" placeholder="basico">
                                    </div>
                                </div>

                                <div class="col-xl-4">
                                    <div class="form-group">
                                        <label class="col-form-label">Email:</label>
                                        <input type="email" class="form-control" name="email" id="email"
                                            placeholder="ejemplo@gmail.com">
                                    </div>
                                </div>
                                <div class="col-xl-4">
                                    <div class="form-group">
                                        <label class="col-form-label">Estado civil:</label>
                                        <select class="form-control" name="estado_civil" id="estado_civil">
                                            <option>SOLTERO</option>
                                            <option>CASADO</option>
                                            <option>VIUDO</option>
                                            <option>DIVORCIADO</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-xl-4">
                                    <div class="form-group">
                                        <label class="col-form-label">Celular:</label>
                                        <input type="text" class="form-control" name="celular" id="celular"
                                            placeholder="999888777">
                                    </div>
                                </div>

                                <div class="col-xl-4">
                                    <div class="form-group">
                                        <label class="col-form-label">Dirección:</label>
                                        <input type="text" class="form-control" name="direccion" id="direccion"
                                            placeholder="Av prolongación">
                                    </div>
                                </div>

                                <div class="col-xl-12">
                                    <div class="form-group">
                                        <label class="col-form-label">Familiar de Contacto:</label>
                                        <input type="text" class="form-control" name="familiar_contacto"
                                            id="familiar_contacto" placeholder="datos del familiar">
                                    </div>
                                </div>



                                <!--DATOS DEL ACOMPAÑANTE SE ACTIVARA CUANDO LE DEMOS CLICK-->
                                <div class="card oculto_card_responsable" id="modal_responsable">
                                    <div class="card-body row">
                                        <div class="col-xl-8">
                                            <div class="form-group">
                                                <label for="col-form-label">Nombres del acompañante:</label>
                                                <input type="text" class="form-control" name="nombre_acompañante"
                                                    id="nombre_acompañante" placeholder="Maria Cardenas">
                                            </div>
                                        </div>

                                        <div class="col-xl-4">
                                            <div class="form-group">
                                                <label for="col-form-label">Telefono del acompañante:</label>
                                                <input type="text" class="form-control" name="telefono_responsable">
                                            </div>

                                        </div>

                                        <div class="col-xl-4">
                                            <div class="form-group">
                                                <label class="col-form-label">Tipo Identificación:</label>
                                                <select class="form-control" name="tipo_identificacion_responsable"
                                                    id="tipo_identificacion_responsable">
                                                    <option value="DNI">DNI</option>
                                                    <option value="CARNET EXTRANJERI">CARNET EXTRANJERIA</option>
                                                    <option value="PTP">PTP</option>
                                                    <option value="TAM">TAM</option>
                                                    <option value="RUC">RUC</option>
                                                    <option value="PASAPORTE">PASAPORTE</option>
                                                    <option value="SIN DOCUMENTOS">SIN DOCUMENTOS</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-xl-4">
                                            <div class="form-group">
                                                <label class="col-form-label">Número de identidad:</label>
                                                <input size="16" type="number" name="numero_identidad_responsable"
                                                    id="numero_identidad_responsable" class="form-control"
                                                    placeholder="67543432">
                                            </div>
                                        </div>

                                        <div class="col-xl-4">
                                            <div class="form-group">
                                                <label class="col-form-label" for="">Responsable:</label>
                                                <select class="form-control" name="responsable" id="responsable">
                                                    <option value="">PAPÁ</option>
                                                    <option value="">MAMÁ</option>
                                                    <option value="">HERMANO</option>
                                                    <option value="">PRIMO</option>
                                                    <option value="">AMIGO</option>
                                                    <option value="">CONOCIDO CERCANO</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger light" data-bs-dismiss="modal">Close</button>
                        <input type="submit" class="btn btn-primary" value="Guardar Datos">
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

<script>
    window.addEventListener('DOMContentLoaded', function() {
        const input = document.querySelector('#numero_identidad');
        input.addEventListener('input', function(event) {
            buscarPaciente(event);
        });
    });

    async function buscarPaciente(event) {
        const valor = event?.target?.value?.trim() || '';
        if (!valor) return;

        try {
            const res = await fetch('http://127.0.0.1:8000/api/patient/show', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    numero_identidad: valor
                }),
            });

            const data = await res.json();
            console.log('Respuesta:', data);

            if(data.message === "encontrado"){
              document.querySelector('#nombre_paciente').value = data.patient.nombre;
              document.querySelector('#apellido_materno').value = data.patient.apellido_materno;
            }

            console.log('Respuesta:', data);
        } catch (error) {
            console.error('Error:', error);
            console.error('⚠️ Error al consultar paciente: ' + error.message);
        }
    }
</script>

<script>
    window.addEventListener('DOMContentLoaded', function() {
        let responsable_id = document.querySelector('#responsable_id');
        let modal_responsable = document.querySelector('#modal_responsable');

        responsable_id.addEventListener('change', function(event) {
            console.log(event);

            if (event.target.checked) {
                console.log('vino con su responsable');
                modal_responsable.classList.remove('oculto_card_responsable')
            } else {
                console.log('no vino con su responsable');
                modal_responsable.classList.add('oculto_card_responsable');
            }
        })
    })
</script>


@endsection