window.addEventListener("DOMContentLoaded", function () { });

// GUARDAR DATOS DEL DOCTOR  Y SUS SERVICIOS 
$("#formCreateDoctorService").on("submit", function (e) {
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


$(document).on("click", ".delete-doctor-service", async function (e) {
    e.preventDefault();

    let interactionMediumId = $(this).data("id");

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
            `${window.location.origin}/master/admin/doctor/services/delete`, {
            method: "POST",
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                id: interactionMediumId,
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