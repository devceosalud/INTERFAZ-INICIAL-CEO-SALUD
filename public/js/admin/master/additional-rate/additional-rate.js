window.addEventListener("DOMContentLoaded", function () {});

// GUARDAR DATOS DE LA TARIFA
$("#formCreateAdditionalRate").on("submit", function (e) {
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

//PARA EDITAR LA ESPECIALIDAD
$(document).on("click", ".edit-additional-rate", async function (e) {
    e.preventDefault();
    let additionalRateId = $(this).data("id");

    try {
        const res = await fetch(
            "http://127.0.0.1:8000/api/admin/additonal-rate/search", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    id: additionalRateId,
                }),
            },
        );

        const data = await res.json();
        console.log("DATOS TARIFA PARA EDITAR:", data);

        if (data.message === "encontrado") {
            let p = data.additionalRate;
            //PINTAR DATOS EN EL MODAL
            $("#additonalRateModalEdit #additional_rate_id_edit").val(p.id);
            $("#additonalRateModalEdit #nombre_edit_tarifa").val(p.nombre);
            $("#additonalRateModalEdit #tipo_edit_tarifa").val(p.tipo_tarifa);
            $("#additonalRateModalEdit #saldo_edit_tarifa").val(p.tarifa);
            //ABRIR MODAL
            $("#additonalRateModalEdit").modal("show");

            initSelectEdit();
        }
    } catch (error) {
        console.error(error);
    }
});

//PARA ACTUALIZAR LOS DATOS DE LA ESPECIALIDAD
$("#formUpdateAdditionalRate").on("submit", function (e) {
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
            } else if (response.code == 2) {
                Swal.fire({
                    icon: "warning",
                    title: "Observado",
                    text: response.msg,
                    timer: 2000,
                    showConfirmButton: false,
                })
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


//FUNCION PARA PODER INICIAR LOS SELECT
function initSelectEdit() {
    //para campos edit
    $("#additonalRateModalEdit #tipo_edit_tarifa").selectpicker("destroy");

    $("#additonalRateModalEdit #tipo_edit_tarifa").selectpicker();
}
