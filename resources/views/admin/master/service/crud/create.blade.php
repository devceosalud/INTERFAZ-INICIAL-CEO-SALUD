<div class="modal fade" id="serviceModalCreate" tabindex="-1" aria-labelledby="serviceModalCreateLabel" aria-hidden="true">

    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header bg-dark">
                <h5 class="modal-title text-white" id="serviceModalCreateLabel">
                    Registro de Servicio
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>

            <form id="formCreateService" method="POST" action="{{ route('master.service.store') }}">

                @csrf

                <div class="modal-body">

                    <!-- DATOS PERSONALES -->
                    <h6 class="fw-bold mb-3">
                        Datos del Servicio
                    </h6>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-primary">Nombre<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombre" id="nombre"
                                placeholder="Nombre completo">

                            <span class="text-danger error-text nombre_error"></span>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-primary">Especialidades<span
                                    class="text-danger">*</span></label>
                            <select class="form-control" name="specialty_id" id="specialty_id">
                                @foreach ($specialties as $specialty)
                                    <option value="{{ $specialty->id }}">{{ $specialty->nombre }}</option>
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
                                @for ($i = 0; $i < $count; $i++)
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
