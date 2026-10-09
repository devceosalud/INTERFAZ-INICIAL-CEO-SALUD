(function (root) {
    async function lookup(url, id, token, fetcher) {
        const response = await fetcher(url, { method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ id }) });
        if (response.redirected || [401, 419].includes(response.status)) {
            throw new Error('La sesión venció. Inicia sesión nuevamente antes de editar usuarios.');
        }
        if (response.status === 403) { throw new Error('No tienes permiso para editar usuarios.'); }
        if (!(response.headers.get('content-type') || '').includes('application/json')) {
            throw new Error('No se pudo cargar el usuario. Recarga la página y vuelve a intentarlo.');
        }
        const body = await response.json();
        if (!response.ok) { throw new Error(response.status === 404 ? 'El usuario ya no está disponible.' : 'No se pudo cargar el usuario.'); }
        return body;
    }
    root.UserEditorApi = { lookup };
    if (typeof module === 'object' && module.exports) { module.exports = root.UserEditorApi; }
}(typeof window !== 'undefined' ? window : globalThis));

if (typeof window !== 'undefined') window.addEventListener("DOMContentLoaded", function () {



    // GUARDAR DATOS DEL USUARIO
    $("#formCreateUser").on("submit", function (e) {
        e.preventDefault();

        let form = this;

        $.ajax({
            url: $(form).attr("action"),
            method: $(form).attr("method"),
            data: new FormData(form),
            processData: false,
            contentType: false,
            dataType: "json",

            beforeSend: function () {
                // Limpiar errores anteriores
                $(form).find("span.error-text").text("");
                // deshabilitar boton de envio
                $(form).find('input[type="submit"]').prop("disabled", true);
            },

            success: function (response) {
                if (response.code == 0) {
                    $.each(response.error, function (prefix, val) {
                        $(form).find("span." + prefix + "_error").text(val[0]);
                    });
                } else {
                    Swal.fire({
                        icon: "success",
                        title: "Correcto",
                        text: response.msg,
                        timer: 2000,
                        showConfirmButton: false,
                    }).then(() => {
                        location.reload();
                    });
                    form.reset();
                    $("#patientModalCreate").modal("hide");
                }
            },

            error: function (xhr) {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "No se pudo guardar el usuario. Comprueba la sesión y los datos.",
                });
            },

            complete: function () {
                $(form).find('input[type="submit"]').prop("disabled", false);
            },
        });
    });


    //PARA EDITAR AL USUARIO
    $(document).on("click", ".edit-user", async function (e) {
        e.preventDefault();
        let userId = $(this).data("id");

        try {
            const data = await window.UserEditorApi.lookup(`${window.location.origin}/api/admin/user/search`, userId,
                document.querySelector('meta[name="csrf-token"]').content, window.fetch.bind(window));

            if (data.message === "encontrado") {
                let u = data.user;
                //PINTAR DATOS EN EL MODAL
                $("#userModalEdit #usuario_id_edit").val(u.id);
                $("#userModalEdit #nombre_usuario_edit").val(u.name);
                $("#userModalEdit #email_edit").val(u.email);
                $("#userModalEdit input[name=password]").val('');

                //ABRIR MODAL
                $("#userModalEdit").modal("show");
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'No se pudo abrir la edición', text: error.message });
        }
    });


    //PARA ACTUALIZAR DATOS DEL USUARIO
    $("#formUpdateUser").on("submit", function (e) {
        e.preventDefault();

        let form = this;

        $.ajax({
            url: $(form).attr("action"),
            method: "POST",
            data: new FormData(form),
            processData: false,
            contentType: false,
            dataType: "json",

            beforeSend: function () {
                $(form).find("span.error-text").text("");
                $(form).find('input[type="submit"]').prop("disabled", true);
            },

            success: function (response) {
                if (response.code == 0) {
                    $.each(response.error, function (prefix, val) {
                        $(form)
                            .find("span." + prefix + "_error")
                            .text(val[0]);
                    });
                } else {
                    Swal.fire({
                        icon: "success",
                        title: "Actualizado",
                        text: response.msg,
                        timer: 2000,
                        showConfirmButton: false,
                    }).then(() => {
                        location.reload();
                    });

                    $("#userModalEdit").modal("hide");
                }
            },

            error: function (xhr) {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Ocurrió un error al actualizar al usuario",
                });
            },

            complete: function () {
                $(form).find('input[type="submit"]').prop("disabled", false);
            },
        });
    });


});
