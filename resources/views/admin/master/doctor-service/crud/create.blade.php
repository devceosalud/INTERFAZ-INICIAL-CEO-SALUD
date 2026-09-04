<div class="modal fade" id="doctorServiceModalCreate" tabindex="-1" aria-labelledby="doctorServiceModalCreateLabel" aria-hidden="true">

    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header bg-dark">
                <h5 class="modal-title text-white" id="doctorServiceModalCreateLabel">
                    Registro nuevo 
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>

            <form id="formCreateDoctorService" method="POST" action="{{ route('master.service.doctor.store') }}">

                @csrf

                <div class="modal-body">

                    <!-- DATOS PERSONALES -->
                    <h6 class="fw-bold mb-3">
                        Datos del Servicio
                    </h6>

                    <div class="row g-3">
                       
                        <div class="col-md-6">
                            <label class="form-label text-primary">Doctores<span
                                    class="text-danger">*</span></label>
                            <select class="form-control" name="doctor_id" id="doctor_id">
                                @foreach ($doctors as $doctor)
                                    <option value="{{ $doctor->id }}">{{ $doctor->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-primary">Servicios<span
                                    class="text-danger">*</span></label>
                            <select class="form-control" name="service_id" id="service_id">
                                @foreach ($services as $service)
                                    <option value="{{ $service->id }}">{{ $service->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Precio Estándar<span
                                    class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="precio_estandar" id="precio_estandar"
                                placeholder="100">

                            <span class="text-danger error-text precio_estandar_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Reconsulta<span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="reconsulta" id="reconsulta"
                                placeholder="80">

                            <span class="text-danger error-text reconsulta_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Días<span class="text-danger">*</span></label>
                            <select class="form-control" name="dias" id="dias">
                                @for ($i = 1; $i < $count; $i++)
                                    <option value=" {{ $i }} ">{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="modal-footer bg-dark">
                    <button type="button" class="btn btn-dark btn-rounded" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <input type="submit" class="btn btn-primary btn-save btn-rounded" value="Guardar Servicio">
                </div>
            </form>

        </div>
    </div>

</div>
