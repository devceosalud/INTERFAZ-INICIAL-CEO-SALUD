window.addEventListener("DOMContentLoaded", function () {});



//PARA EDITAR LA ESPECIALIDAD
$(document).on("click", ".edit-responsible", async function (e) {
    e.preventDefault();
    let doctorId = $(this).data("id");

    try {
        const res = await fetch(
            `${window.location.origin}/api/admin/responsible/search`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    id: doctorId,
                }),
            },
        );

        const data = await res.json();
        console.log("DATOS RESPONSABLE PARA EDITAR:", data);

        if (data.message === "encontrado") {
            let p = data.responsible;
            //PINTAR DATOS EN EL MODAL
            $("#responsibleModalEdit #responsible_id_edit").val(p.id);
            $("#responsibleModalEdit #responsable_tipo").val(p.parentezco);
            $("#responsibleModalEdit #nombre_responsable").val(p.nombres);
            $("#responsibleModalEdit #telefono_responsable").val(p.telefono);
            $("#responsibleModalEdit #tipo_identificacion_responsable").val(p.tipo_identificacion);
            $("#responsibleModalEdit #numero_identidad_responsable").val(p.numero_identidad);
            //ABRIR MODAL
            $("#responsibleModalEdit").modal("show");

            initSelectEdit();
        }
    } catch (error) {
        console.error(error);
    }
});

//PARA ACTUALIZAR LOS DATOS DE LA ESPECIALIDAD
$("#formUpdateResponsible").on("submit", function (e) {
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


// PARA INACTIVAR AL RESPONSABLE
$(document).on("click", ".delete-responsible", async function (e) {
    e.preventDefault();

    let responsibleId = $(this).data("id");

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
            `${window.location.origin}/admissionist/responsible/delete`, {
                method: "POST",
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    id: responsibleId,
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
    $("#responsibleModalEdit #responsable_tipo").selectpicker("destroy");
    $("#responsibleModalEdit #tipo_identificacion_responsable").selectpicker("destroy");

    $("#responsibleModalEdit #responsable_tipo").selectpicker();
    $("#responsibleModalEdit #tipo_identificacion_responsable").selectpicker();
}
