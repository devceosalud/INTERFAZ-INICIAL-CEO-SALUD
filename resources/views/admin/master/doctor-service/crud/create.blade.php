<div class="modal fade catalog-modal" id="doctorServiceModalCreate" tabindex="-1" aria-labelledby="doctorServiceModalCreateLabel" aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-scrollable">
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

                    <p class="alert alert-info" data-price-source></p><div class="row g-3">@include('admin.master.doctor-service.crud.specialty-authorization')
                       
                        <div class="col-md-6">
                            <label class="form-label text-primary">Profesional<span
                                    class="text-danger">*</span></label>
                            <select class="form-control" name="doctor_id" id="doctor_id">
                                @foreach ($doctors as $doctor)
                                    <option data-specialty="{{ $doctor->specialty_id }}" value="{{ $doctor->id }}" @selected((string)$selectedDoctorId === (string)$doctor->id)>{{ $doctor->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-primary">Servicios<span
                                    class="text-danger">*</span></label>
                            <select class="form-control" name="service_id" id="service_id">
                                <option value="">Seleccione un servicio compatible</option>@foreach ($services as $service)
                                    <option value="{{ $service->id }}" data-specialties="{{ json_encode(array_merge([(int)$service->specialty_id],$service->sharedSpecialties->filter(fn($s)=>$s->pivot->estado==='ACTIVO')->pluck('id')->map(fn($id)=>(int)$id)->all())) }}" data-policy="{{ $service->pricing_policy }}" data-price="{{ $service->institutional_price }}">{{ $service->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Consulta / precio base S/<span
                                    class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="precio_estandar" id="precio_estandar"
                                min="0.01" step="0.01">

                            <span class="text-danger error-text precio_estandar_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Reevaluación S/ (opcional)</label>
                            <input type="number" class="form-control" name="reconsulta" id="reconsulta"
                                min="0.01" step="0.01">

                            <small class="text-muted">Déjalo vacío si este servicio no tiene tarifa de reevaluación. No habilita una reevaluación gratuita.</small><span class="text-danger error-text reconsulta_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label text-primary">Días<span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="dias" id="dias" value="15" readonly><small>El límite clínico aprobado es de 15 días.</small>
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
