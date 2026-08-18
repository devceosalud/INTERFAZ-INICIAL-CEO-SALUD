window.addEventListener("DOMContentLoaded", function () { });


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
            $(form).find("span.error-text").text(""); // Limpiar errores anteriores
            $(form).find('input[type="submit"]').prop("disabled", true);// deshabilitar boton de envio
        },

        success: function (response) {
            if (response.code == 0) {
                $.each(response.error, function (prefix, val) {
                    $(form).find("span." + prefix + "_error").text(val[0]);
                    console.log("span." + prefix + "_error");
                    console.log(val[0]);
                });
            } else {
                notificacion("success", "Correcto", response.msg, 2000, false, true);
            }
        },

        error: function (xhr) {
            console.log(xhr.responseText);
            notificacion("error", "Error", xhr.responseTextg, 4000, false, false);
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
        });

        const data = await res.json();
        console.log("DATOS DOCTOR HORARIOS PARA EDITAR:", data);

        if (data.message === "encontrado") {
            let p = data.doctor_schedule;
            //PINTAR DATOS EN EL MODAL
            $("#doctorScheduleModalEdit #doctor_schedule_id_edit").val(p.id);
            $("#doctorScheduleModalEdit #doctor_id_edit").val(p.doctor_id);
            $("#doctorScheduleModalEdit #dia_semana_edit").val(p.dia_semana);
            $("#doctorScheduleModalEdit #hora_inicio_edit").val(p.hora_inicio.substring(0, 5));
            $("#doctorScheduleModalEdit #hora_fin_edit").val(p.hora_fin.substring(0, 5));
            $("#doctorScheduleModalEdit #duracion_edit_cita").val(p.duracion_cita);
            $("#doctorScheduleModalEdit").modal("show");//ABRIR MODAL

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
                    $(form).find("span." + prefix + "_error").text(val[0]);
                    console.log("span." + prefix + "_error");
                    console.log(val[0]);
                });
            } else if (response.code == 2) {
                notificacion("warning", "Observado", response.msg, 2000, false, false);
            } else {
                notificacion("success", "Actualizado", response.msg, 2000, false, true);
            }
        },

        error: function (xhr) {
            console.log(xhr.responseText);
            notificacion("error", "Error", xhr.responseTextg, 4000, false, false);
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
        const res = await fetch(`${window.location.origin}/admissionist/doctor-schedule/delete`, {
            method: "POST",
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                id: doctorScheduleId,
            }),
        });

        const data = await res.json();

        if (data.code === 1) {
            notificacion("success", "¡Inactivado!", data.msg, 1500, false, true);
        } else {
            notificacion("error", "Error", data.msg, 4000, false, false);
        }

    } catch (error) {
        console.error(error);
        notificacion("error", "Error", error, 4000, false, false);
    }
});


function notificacion(icon, title, text, timer, showConfirmButton, recargar) {
    Swal.fire({
        position: 'top-end',
        icon: icon,
        title: title,
        text: text,
        timer: timer,
        showConfirmButton: showConfirmButton,
    }).then(() => {
        if (recargar) { location.reload(); }
    });
}

//FUNCION PARA PODER INICIAR LOS SELECT
function initSelectEdit() {
    //para campos edit
    $("#doctorScheduleModalEdit #doctor_id_edit").selectpicker("refresh");
    $("#doctorScheduleModalEdit #dia_semana_edit").selectpicker("refresh");
    $("#doctorScheduleModalEdit #duracion_edit_cita").selectpicker("refresh");
}
