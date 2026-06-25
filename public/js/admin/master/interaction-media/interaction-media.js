window.addEventListener("DOMContentLoaded", function () {
});

// GUARDAR DATOS DEL MEDIO
$("#formCreateInteractionMedia").on("submit", function (e) {
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
                    $(form)
                        .find("span." + prefix + "_error")
                        .text(val[0]);
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

//PARA EDITAR EL MEDIO
$(document).on("click", ".edit-interaction-medium", async function (e) {
    e.preventDefault();
    let interactionMediId = $(this).data("id");

    try {
        const res = await fetch(
            "http://127.0.0.1:8000/api/admin/interaction-media/search",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    id: interactionMediId,
                }),
            },
        );

        const data = await res.json();
        console.log("DATOS CANAL PARA EDITAR:", data);

        if (data.message === "encontrado") {
            let p = data.interactionMedium;
            //PINTAR DATOS EN EL MODAL
            $("#interactionMediaModalEdit #interaction_media_id_edit").val(p.id);
            $("#interactionMediaModalEdit #nombre_edit_interaccion_medio").val(p.nombre);
            //ABRIR MODAL
            $("#interactionMediaModalEdit").modal("show");
        }
    } catch (error) {
        console.error(error);
    }
});

//PARA ACTUALIZAR LOS DATOS DEL MEDIO
$("#formUpdateInteractionMedia").on("submit", function (e) {
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

                $("#nombre_edit_interaccion_medio").modal("hide");
            }
        },

        error: function (xhr) {
            console.log(xhr.responseText);
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "Ocurrió un error al actualizar el paciente",
            });
        },

        complete: function () {
            $(form).find('input[type="submit"]').prop("disabled", false);
        },
    });
});
