<div class="modal fade" id="specialtytModalCreate" tabindex="-1" aria-labelledby="specialtytModalCreateLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="specialtytModalCreateLabel">Agregar nueva Nueva Especialidad:</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <form id="formCreateAppointment" action="{{ route('master.specialty.store') }}" method="POST">

                    @csrf
                    <div class="row">

                        <!-- ================= ESPECIALIDAD ================= -->
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Nombre de la especialidad</label>
                                <input type="text" class="form-control" id="nombre_especialidad" name="nombre_especialidad" placeholder="Ejemplo: cardiología">
                            </div>
                        </div>


                        <!-- ================= BOTON ================= -->
                        <div class="col-12 text-end mt-4">
                            <input type="submit" class="btn btn-primary btn-rounded" value="Registrar la especialidad">
                        </div>

                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger light btn-rounded" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
