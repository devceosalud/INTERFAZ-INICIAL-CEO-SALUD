<div class="modal fade" id="userModalEdit" tabindex="-1" aria-labelledby="userModalEditLabel" aria-hidden="true">

    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header">
                <h5 class="modal-title" id="userModalEditLabel">
                    Actualizar Usuario
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>

            <form id="formUpdateUser" method="POST" action="{{ route('admin.user.update.list') }}">

                @method('put')
                @csrf

                <div class="modal-body">

                    <!-- DATOS PERSONALES -->
                    <h6 class="fw-bold mb-3">
                        Datos del Usuario
                    </h6>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Nombre</label>
                            <input type="hidden" name="usuario_id_edit" id="usuario_id_edit">
                            <input type="text" class="form-control" name="nombre_usuario_edit" id="nombre_usuario_edit"
                                placeholder="Nombre completo">

                            <span class="text-danger error-text nombre_usuario_edit_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Correo electrónico</label>
                            <input type="email" class="form-control" name="email_edit" id="email_edit"
                                placeholder="correo@ceosalud.com">
                            <span class="text-danger error-text email_edit_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Nueva contraseña</label>
                            <input type="password" name="password" class="form-control"
                                placeholder="Dejar vacío para mantener la actual">
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-dark btn-rounded" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <input type="submit" class="btn btn-primary btn-rounded" value="Actualizar Usuario">
                </div>
            </form>

        </div>
    </div>

</div>
