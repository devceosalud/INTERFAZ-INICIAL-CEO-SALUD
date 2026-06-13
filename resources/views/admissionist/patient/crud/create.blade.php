 <div class="modal fade" id="patientModalCreate" tabindex="-1" aria-labelledby="patientModalCreateLabel" aria-hidden="true">
     <div class="modal-dialog modal-lg" role="document">
         <div class="modal-content">
             <div class="modal-header">
                 <h5 class="modal-title" id="patientModalCreateLabel">Paciente</h5>
                 <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                 </button>
             </div>
             <div class="modal-body">
                 <form id="formCreatePatient" method="POST" action="{{ route('admissionit.patient.store') }}">

                     @csrf
                     <div class="row">

                         <div class="col-xl-12">
                             <div class="form-group">
                                 <div class="d-flex justify-content-between">
                                     <label class="col-form-label">Nombre:</label>

                                     <div>
                                         <input type="checkbox" id="responsable_id" name="responsable_id"
                                             value="responsable">
                                         <label for="responsable_id"> Viene acompañado?</label><br>
                                     </div>
                                 </div>
                                 <input type="text" class="form-control" name="nombre_paciente" id="nombre_paciente"
                                     placeholder="Name">

                                 {{-- alerta de error --}}
                                 <span class="text-danger error-text nombre_paciente_error"></span>
                             </div>
                         </div>

                         <div class="col-xl-4">
                             <div class="form-group">
                                 <label class="col-form-label">Apellido Paterno:</label>
                                 <input type="text" class="form-control" name="apellido_paterno"
                                     id="apellido_paterno" placeholder="Apellido Paterno">
                                 {{-- alerta de error --}}
                                 <span class="text-danger error-text apellido_paterno_error"></span>
                             </div>
                         </div>

                         <div class="col-xl-4">
                             <div class="form-group">
                                 <label class="col-form-label">Apellido Materno:</label>
                                 <input type="text" class="form-control" name="apellido_materno"
                                     id="apellido_materno" placeholder="Apellido Materno">
                                 {{-- alerta de error --}}
                                 <span class="text-danger error-text apellido_materno_error"></span>
                             </div>
                         </div>

                         <div class="col-xl-4">
                             <div class="form-group">
                                 <label class="col-form-label">Genero:</label>
                                 <select class="form-control" name="genero_paciente" id="genero_paciente">
                                     <option value="HOMBRE">HOMBRE</option>
                                     <option value="MUJER">MUJER</option>
                                 </select>
                             </div>
                         </div>

                         <div class="col-xl-4">
                             <div class="form-group">
                                 <label class="col-form-label">Tipo Identificación:</label>
                                 <select class="form-control" name="tipo_identificacion" id="tipo_identificacion">
                                     <option value="DNI">DNI</option>
                                     <option value="CARNET EXTRANJERIA">CARNET EXTRANJERIA</option>
                                     <option value="PTP">PTP</option>
                                     <option value="TAM">TAM</option>
                                     <option value="RUC">RUC</option>
                                     <option value="PASAPORTE">PASAPORTE</option>
                                     <option value="SALVOCONDUCTO">SALVOCONDUCTO</option>
                                     <option value="SIN DOCUMENTOS">SIN DOCUMENTOS</option>
                                 </select>
                             </div>
                         </div>


                         <div class="col-xl-4">
                             <div class="form-group">
                                 <label class="col-form-label">Número de identidad:</label>
                                 <input type="text" name="numero_identidad" id="numero_identidad"
                                     class="form-control" placeholder="987654321">
                                 {{-- alerta de error --}}
                                 <span class="text-danger error-text numero_identidad_error"></span>
                             </div>
                         </div>

                         <div class="col-xl-4">
                             <div class="form-group">
                                 <label class="col-form-label">Fecha de Nacimiento:</label>
                                 <input type="date" class="form-control" name="fecha_nacimiento"
                                     id="fecha_nacimiento">
                                 {{-- alerta de error --}}
                                 <span class="text-danger error-text fecha_nacimiento_error"></span>
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
                                 {{-- alerta de error --}}
                                 <span class="text-danger error-text email_error"></span>
                             </div>
                         </div>
                         <div class="col-xl-4">
                             <div class="form-group">
                                 <label class="col-form-label">Estado civil:</label>
                                 <select class="form-control" name="estado_civil" id="estado_civil">
                                     <option value="SOLTERO">SOLTERO</option>
                                     <option value="CASADO">CASADO</option>
                                     <option value="VIUDO">VIUDO</option>
                                     <option value="DIVORCIADO">DIVORCIADO</option>
                                 </select>
                             </div>
                         </div>

                         <div class="col-xl-4">
                             <div class="form-group">
                                 <label class="col-form-label">Telefono:</label>
                                 <input type="text" class="form-control" name="telefono" id="telefono"
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
                                         <input type="text" class="form-control" name="nombre_responsable"
                                             id="nombre_responsable" placeholder="Maria Cardenas">
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
                                         <select class="form-control" name="responsable_tipo" id="responsable_tipo">
                                             <option value="PAPA">PAPÁ</option>
                                             <option value="MAMA">MAMÁ</option>
                                             <option value="HERMANO">HERMANO</option>
                                             <option value="PRIMO">PRIMO</option>
                                             <option value="AMIGO">AMIGO</option>
                                             <option value="CONOCIDO CERCANO">CONOCIDO CERCANO</option>
                                         </select>
                                     </div>
                                 </div>
                             </div>
                         </div>

                     </div>

                     <input type="submit" class="btn btn-primary" value="Guardar Datos">
                 </form>
             </div>
             <div class="modal-footer">
                 <button type="button" class="btn btn-danger light" data-bs-dismiss="modal">Cerrar</button>

             </div>
         </div>
     </div>
 </div>
