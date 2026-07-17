window.addEventListener("DOMContentLoaded", function () {});

// GUARDAR DATOS DEL DOCTOR
$("#formCreateService").on("submit", function (e) {
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

//PARA EDITAR EL SERVICIO
$(document).on("click", ".edit-service", async function (e) {
    e.preventDefault();
    let serviceId = $(this).data("id");

    try {
        const res = await fetch(
            `${window.location.origin}/api/admin/service/search`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    id: serviceId,
                }),
            },
        );

        const data = await res.json();
        console.log("DATOS SERVICIO PARA EDITAR:", data);

        if (data.message === "encontrado") {
            let p = data.service;
            //PINTAR DATOS EN EL MODAL
            $("#serviceModalEdit #service_id_edit").val(p.id);
            $("#serviceModalEdit #nombre_edit").val(p.nombre);
            $("#serviceModalEdit #specialty_id_edit").val(p.specialty_id);
            $("#serviceModalEdit #precio_estandar_edit").val(p.precio_primera_consulta);
            $("#serviceModalEdit #reconsulta_edit").val(p.precio_reconsulta);
            $("#serviceModalEdit #dias_edit").val(p.dias_reconsulta);
            //ABRIR MODAL
            $("#serviceModalEdit").modal("show");

            initSelectEdit();
        }
    } catch (error) {
        console.error(error);
    }
});

//PARA ACTUALIZAR LOS DATOS DE LA ESPECIALIDAD
$("#formUpdateService").on("submit", function (e) {
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


//PARA DESACTIVAR EL SERVICIO
$(document).on("click", ".delete-service", async function (e) {
    e.preventDefault();

    let serviceId = $(this).data("id");

    const result = await Swal.fire({
        title: "¿Está seguro de inactivar?",
        text: "¡Esta acción es irreversible!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Sí, inactivar",
        cancelButtonText: "Cancelar"
    });

    if (!result.isConfirmed) {
        return;
    }

    try {
        const res = await fetch(
            `${window.location.origin}/master/admin/service/delete`, {
                method: "POST",
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    id: serviceId,
                }),
            }
        );

        const data = await res.json();

        if (data.code === 1) {
            Swal.fire({
                title: "¡Inactivado!",
                text: data.msg,
                icon: "success",
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                title: "Error",
                text: data.msg,
                icon: "error"
            });
        }

    } catch (error) {
        console.error(error);

        Swal.fire({
            title: "Error",
            text: "Ocurrió un error al procesar la solicitud",
            icon: "error"
        });
    }
});

//FUNCION PARA PODER INICIAR LOS SELECT
function initSelectEdit() {
    //para campos edit
    $("#serviceModalEdit #specialty_id_edit").selectpicker("destroy");
    $("#serviceModalEdit #dias_edit").selectpicker("destroy");

    $("#serviceModalEdit #specialty_id_edit").selectpicker();
    $("#serviceModalEdit #dias_edit").selectpicker();
}
