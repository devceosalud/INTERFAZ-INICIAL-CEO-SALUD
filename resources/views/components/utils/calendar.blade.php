<div>
    <div class="row">
        <div class="col-md-6">
            <label class="form-label text-primary">Especialidad <span class="text-danger">*</span></label>
            <select class="form-control" id="filtro-calendar_specialty_id">
                <option value="">Seleccione</option>

                @foreach ($specialties as $specialty)
                    <option value="{{ $specialty->id }}">
                        {{ $specialty->nombre }}
                    </option>
                @endforeach
            </select>

            <span class="text-danger error-text specialty_id_error"></span>
        </div>

        <div class="col-md-6">
            <label class="form-label text-primary">Médico <span class="text-danger">*</span></label>
            <select class="form-control" name="filtro-calendar_doctor_id" id="filtro-calendar_doctor_id">
                <option value="">Seleccione</option>
            </select>

            <span class="text-danger error-text doctor_id_error"></span>
        </div>


        <div class="col-xl-12 col-xxl-12 mt-2">
            <div class="calendar-container">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
</div>
