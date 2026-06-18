<div class="modal fade" id="userModalCreate" tabindex="-1" aria-labelledby="userModalCreateLabel" aria-hidden="true">

    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header">
                <h5 class="modal-title" id="userModalCreateLabel">
                    Registrar Usuario
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>

            <form id="formCreateUser" method="POST" action="{{ route('admin.user.store') }}">

                @csrf

                <div class="modal-body">

                    <!-- DATOS PERSONALES -->
                    <h6 class="fw-bold mb-3">
                        Datos del Usuario
                    </h6>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" name="nombre_usuario" id="nombre_usuario"
                                placeholder="Nombre completo">

                            <span class="text-danger error-text nombre_usuario_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Correo electrónico</label>
                            <input type="email" class="form-control" name="email" id="email"
                                placeholder="correo@ceosalud.com">
                            <span class="text-danger error-text email_error"></span>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Contraseña</label>
                            <input type="password" name="password" id="password" class="form-control"
                                placeholder="*****">
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-dark btn-rounded" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <input type="submit" class="btn btn-primary btn-rounded" value="Registrar Usuario">
                </div>
            </form>

        </div>
    </div>

</div>
