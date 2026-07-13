<div class="modal fade" id="doctorModalCreate" tabindex="-1" aria-labelledby="doctorModalCreateLabel" aria-hidden="true">

    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header bg-dark">
                <h5 class="modal-title text-white" id="doctorModalCreateLabel">
                    Registro de Doctores
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>

            <form id="formCreateDoctor" method="POST" action="{{ route('master.doctor.store') }}">

                @csrf

                <div class="modal-body">

                    <!-- DATOS PERSONALES -->
                    <h6 class="fw-bold mb-3">
                        Datos del Doctor
                    </h6>

                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label text-primary">Nombres<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombres" id="nombres"
                                placeholder="Nombre completo">

                            <span class="text-danger error-text nombres_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Especialidades<span
                                    class="text-danger">*</span></label>
                            <select class="form-control" name="specialty_id" id="specialty_id">
                                @foreach ($specialties as $specialty)
                                    <option value=" {{ $specialty->id }} ">{{ $specialty->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">CMP<span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="cmp" id="cmp">

                            <span class="text-danger error-text cmp_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">RNE<span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="rne" id="rne">

                            <span class="text-danger error-text rne_error"></span>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="modal-footer bg-dark">
                    <button type="button" class="btn btn-dark btn-rounded" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <input type="submit" class="btn btn-primary btn-save btn-rounded" value="Guardar Doctor">
                </div>
            </form>

        </div>
    </div>

</div>
