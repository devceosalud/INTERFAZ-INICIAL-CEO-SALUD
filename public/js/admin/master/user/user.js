window.addEventListener("DOMContentLoaded", function () {

    console.log('CARGANDO USUARIOS');


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
                        console.log("span." + prefix + "_error");
                        console.log(val[0]);
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
                console.log(xhr.responseText);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Ocurrió un error al guardar el paciente",
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
            const res = await fetch(
                `${window.location.origin}/api/admin/user/search`, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        id: userId,
                    }),
                },
            );

            const data = await res.json();
            console.log("DATOS PARA EDITAR:", data);

            if (data.message === "encontrado") {
                let u = data.user;
                //PINTAR DATOS EN EL MODAL
                $("#userModalEdit #usuario_id_edit").val(u.id);
                $("#userModalEdit #nombre_usuario_edit").val(u.name);
                $("#userModalEdit #email_edit").val(u.email);

                //ABRIR MODAL
                $("#userModalEdit").modal("show");
            }
        } catch (error) {
            console.error(error);
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
                        console.log("span." + prefix + "_error");
                        console.log(val[0]);
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
                console.log(xhr.responseText);
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
