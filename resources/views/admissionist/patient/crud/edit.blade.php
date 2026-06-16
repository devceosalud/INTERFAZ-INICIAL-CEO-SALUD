 <div class="modal fade" id="patientModalEdit" tabindex="-1" aria-labelledby="patientModalEditLabel" aria-hidden="true">
     <div class="modal-dialog modal-lg" role="document">
         <div class="modal-content">
             <div class="modal-header">
                 <h5 class="modal-title" id="patientModalEditLabel">Actualizar Paciente</h5>
                 <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                 </button>
             </div>
             <div class="modal-body">
                 <form id="formUpdatePatient" method="POST" action="{{ route('admissionit.patient.update') }}">

                     @method('put')

                     @csrf
                     <div class="row">

                         <div class="col-xl-12">
                             <div class="form-group">
                                 <label class="col-form-label">Nombre:</label>
                                 <input type="hidden" name="id" id="patient_id_edit">
                                 <input type="text" class="form-control" name="nombre_paciente_edit"
                                     id="nombre_paciente_edit" placeholder="Name">
                                 {{-- alerta de error --}}
                                 <span class="text-danger error-text nombre_paciente_edit_error"></span>
                             </div>
                         </div>

                         <div class="col-xl-4">
                             <div class="form-group">
                                 <label class="col-form-label">Apellido Paterno:</label>
                                 <input type="text" class="form-control" name="apellido_paterno_edit"
                                     id="apellido_paterno_edit" placeholder="Apellido Paterno">
                             </div>
                         </div>

                         <div class="col-xl-4">
                             <div class="form-group">
                                 <label class="col-form-label">Apellido Materno:</label>
                                 <input type="text" class="form-control" name="apellido_materno_edit"
                                     id="apellido_materno_edit" placeholder="Apellido Materno">
                             </div>
                         </div>

                         <div class="col-xl-4">
                             <div class="form-group">
                                 <label class="col-form-label">Genero:</label>
                                 <select class="form-control" name="genero_paciente_edit" id="genero_paciente_edit">
                                     <option value="HOMBRE">HOMBRE</option>
                                     <option value="MUJER">MUJER</option>
                                 </select>
                             </div>
                         </div>

                         <div class="col-xl-4">
                             <div class="form-group">
                                 <label class="col-form-label">Tipo Identificación:</label>
                                 <select class="form-control" name="tipo_identificacion_edit"
                                     id="tipo_identificacion_edit">
                                     <option value="DNI">DNI</option>
                                     <option value="CARNET EXTRANJERIA">CARNET EXTRANJERIA</option>
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
                                 <input type="text" name="numero_identidad_edit" id="numero_identidad_edit"
                                     class="form-control" placeholder="987654321">
                             </div>
                         </div>

                         <div class="col-xl-4">
                             <div class="form-group">
                                 <label class="col-form-label">Fecha de Nacimiento:</label>
                                 <input type="date" class="form-control" name="fecha_nacimiento_edit"
                                     id="fecha_nacimiento_edit">
                             </div>
                         </div>

                         <div class="col-xl-4">
                             <div class="form-group">
                                 <label class="col-form-label">Ocupación:</label>
                                 <input type="text" class="form-control" name="ocupacion_edit" id="ocupacion_edit"
                                     placeholder="">
                             </div>
                         </div>

                         <div class="col-xl-4">
                             <div class="form-group">
                                 <label class="col-form-label">Grado de Instrucción</label>
                                 <input type="text" class="form-control" name="grado_instruccion_edit"
                                     id="grado_instruccion_edit" placeholder="basico">
                             </div>
                         </div>

                         <div class="col-xl-4">
                             <div class="form-group">
                                 <label class="col-form-label">Email:</label>
                                 <input type="email" class="form-control" name="email_edit" id="email_edit"
                                     placeholder="ejemplo@gmail.com">
                             </div>
                         </div>
                         <div class="col-xl-4">
                             <div class="form-group">
                                 <label class="col-form-label">Estado civil:</label>
                                 <select class="form-control" name="estado_civil_edit" id="estado_civil_edit">
                                     <option value="CASADO">CASADO</option>
                                     <option value="SOLTERO">SOLTERO</option>
                                     <option value="VIUDO">VIUDO</option>
                                     <option value="DIVORCIADO">DIVORCIADO</option>
                                 </select>
                             </div>
                         </div>

                         <div class="col-xl-4">
                             <div class="form-group">
                                 <label class="col-form-label">Telefono:</label>
                                 <input type="text" class="form-control" name="telefono_edit" id="telefono_edit"
                                     placeholder="999888777">
                             </div>
                         </div>

                         <div class="col-md-4">
                             <label class="form-label">Canal</label>
                             <select class="form-control" name="channel_edit" id="channel_edit">
                                 <option value="">Seleccione</option>
                                 @foreach ($channels as $channel)
                                     <option value="{{ $channel->id }}">
                                         {{ $channel->nombre }}
                                     </option>
                                 @endforeach
                             </select>
                         </div>

                         <div class="col-md-4">
                             <label class="form-label">Medio Interacción</label>
                             <select class="form-control" name="interaction_medium_edit" id="interaction_medium_edit">
                                 <option value="">Seleccione</option>
                                 @foreach ($interaction_media as $medium)
                                     <option value="{{ $medium->id }}">
                                         {{ $medium->nombre }}
                                     </option>
                                 @endforeach
                             </select>
                         </div>

                         <div class="col-xl-4">
                             <div class="form-group">
                                 <label class="col-form-label">Dirección:</label>
                                 <input type="text" class="form-control" name="direccion_edit"
                                     id="direccion_edit" placeholder="Av prolongación">
                             </div>
                         </div>

                         <div class="col-xl-12">
                             <div class="form-group">
                                 <label class="col-form-label">Familiar de Contacto:</label>
                                 <input type="text" class="form-control" name="familiar_contacto_edit"
                                     id="familiar_contacto_edit" placeholder="datos del familiar">
                             </div>
                         </div>
                     </div>

                     <input type="submit" class="btn btn-primary" value="Actualizar Paciente">
                 </form>
             </div>
             <div class="modal-footer">
                 <button type="button" class="btn btn-danger light" data-bs-dismiss="modal">Cerrar</button>

             </div>
         </div>
     </div>
 </div>
