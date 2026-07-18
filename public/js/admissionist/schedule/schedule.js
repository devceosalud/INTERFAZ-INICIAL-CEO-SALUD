window.addEventListener("DOMContentLoaded", function () {
});


// GUARDAR DATOS DE LA ESPECIALIDAD
$("#formCreateDoctorSchedule").on("submit", function (e) {
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
                text: "Ocurrió un error al guardar el horario",
            });
        },

        complete: function () {
            $(form).find('input[type="submit"]').prop("disabled", false);
        },
    });
});

//PARA EDITAR EL HORARIO
$(document).on("click", ".edit-doctor-schedule", async function (e) {
    e.preventDefault();
    let doctorScheduleId = $(this).data("id");

    try {
        const res = await fetch(`${window.location.origin}/api/appointment/doctor-schedule/search`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    id: doctorScheduleId,
                }),
            },
        );

        const data = await res.json();
        console.log("DATOS DOCTOR HORARIOS PARA EDITAR:", data);

        if (data.message === "encontrado") {
            let p = data.doctor_schedule;
            //PINTAR DATOS EN EL MODAL
            $("#doctorScheduleModalEdit #doctor_schedule_id_edit").val(p.id);
            $("#doctorScheduleModalEdit #doctor_id_edit").val(p.doctor_id);
            $("#doctorScheduleModalEdit #dia_semana_edit").val(p.dia_semana);
            $("#doctorScheduleModalEdit #hora_inicio_edit").val(p.hora_inicio);
            $("#doctorScheduleModalEdit #hora_fin_edit").val(p.hora_fin);
            $("#doctorScheduleModalEdit #duracion_edit_cita").val(p.duracion_cita);
            //ABRIR MODAL
            $("#doctorScheduleModalEdit").modal("show");

            initSelectEdit();
        }
    } catch (error) {
        console.error(error);
    }
});

//PARA ACTUALIZAR LOS DATOS DEL HORARIO
$("#formUpdateDoctorSchedule").on("submit", function (e) {
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
                text: "Ocurrió un error al actualizar el horario del doctor",
            });
        },

        complete: function () {
            $(form).find('input[type="submit"]').prop("disabled", false);
        },
    });
});



$(document).on("click", ".delete-doctor-schedule", async function (e) {
    e.preventDefault();

    let doctorScheduleId = $(this).data("id");

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
            `${window.location.origin}/admissionist/doctor-schedule/delete`, {
                method: "POST",
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    id: doctorScheduleId,
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
    $("#doctorScheduleModalEdit #doctor_id_edit").selectpicker("destroy");
    $("#doctorScheduleModalEdit #dia_semana_edit").selectpicker("destroy");
    $("#doctorScheduleModalEdit #duracion_edit_cita").selectpicker("destroy");

    $("#doctorScheduleModalEdit #doctor_id_edit").selectpicker();
    $("#doctorScheduleModalEdit #dia_semana_edit").selectpicker();
    $("#doctorScheduleModalEdit #duracion_edit_cita").selectpicker();
}

