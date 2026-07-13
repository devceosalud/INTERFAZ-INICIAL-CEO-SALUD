<div class="modal fade" id="doctorModalEdit" tabindex="-1" aria-labelledby="doctorModalEditLabel" aria-hidden="true">

    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header bg-dark">
                <h5 class="modal-title text-white" id="doctorModalEditLabel">
                    Actualizar Doctor
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>

            <form id="formUpdateDoctor" method="POST" action="{{ route('master.doctor.update') }}">

                @method('put')
                @csrf

                <div class="modal-body">

                    <!-- DATOS PERSONALES -->
                    <h6 class="fw-bold mb-3">
                        Datos del Doctor
                    </h6>

                    <div class="row g-3">
                        <div class="col-md-12">
                            <input type="hidden" name="doctor_id_edit" id="doctor_id_edit">
                            <label class="form-label text-primary">Nombres<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nombres_edit" id="nombres_edit"
                                placeholder="Nombre completo">

                            <span class="text-danger error-text nombres_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Especialidades<span
                                    class="text-danger">*</span></label>
                            <select class="form-control" name="specialty_id_edit" id="specialty_id_edit">
                                @foreach ($specialties as $specialty)
                                    <option value="{{ $specialty->id }}">{{ $specialty->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">CMP<span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="cmp_edit" id="cmp_edit">

                            <span class="text-danger error-text cmp_edit_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">RNE<span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="rne_edit" id="rne_edit">

                            <span class="text-danger error-text rne_edit_error"></span>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="modal-footer bg-dark">
                    <button type="button" class="btn btn-dark btn-rounded" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <input type="submit" class="btn btn-primary btn-save btn-rounded" value="Actualizar Doctor">
                </div>
            </form>

        </div>
    </div>

</div>
