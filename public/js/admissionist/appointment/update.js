$(document).on("click", ".update-appointment", function (e) {
    e.preventDefault();

    //ABRIMOS EL MODAL DE EDITAR LA AGENDA Y ASIGNAMOS EL ID
    $("#appointmentModalState").modal("show");
    let appointmentId = $(this).data("id");
    let estado_cita = $('#appointmentModalState #appointment_id').val(appointmentId);

});


//PARA ACTUALIZAR EL ESTADO DE LA CITA
$("#formUpdateAppointmentState").on("submit", function (e) {
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
            } else {
                notificacion("success", "Actualizado", response.msg, 2000, false, true);
            }
        },

        error: function (xhr) {
            console.log(xhr.responseText);
            notificacion("error", "Error", xhr.responseText, 4000, false, false);
        },

        complete: function () {
            $(form).find('input[type="submit"]').prop("disabled", false);
        },
    });
});


//FUNCION ALERTA
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