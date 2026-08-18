window.addEventListener("DOMContentLoaded", function () {
    console.log('CARGANDO PACIENTES');

    const input = document.querySelector("#patientModalCreate #formCreatePatient #numero_identidad");
    const responsable_id = document.querySelector("#patientModalCreate #responsable_id");
    const modal_responsable = document.querySelector("#patientModalCreate #modal_responsable");


    //PARA BUSCAR AL PACIENTE QUE SE DIGITA
    input.addEventListener("input", function (event) {
        buscarPaciente(event);
    });

    //PARA QUE ME MUESTRE EL FORMULARIO
    responsable_id.addEventListener("change", function (event) {
        responsable(event);
    });

});

// GUARDAR DATOS DEL PACIENTE
$("#formCreatePatient").on("submit", function (e) {
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
            $(form).find('input[type="submit"]').prop("disabled", true);  // deshabilitar boton de envio
        },

        success: function (response) {
            if (response.code == 0) {
                $.each(response.error, function (prefix, val) {
                    $(form).find("span." + prefix + "_error").text(val[0]);
                    console.log("span." + prefix + "_error");
                    console.log(val[0]);
                });
            } else {
                notificacion("success", "Correcto", response.msg, 2000, false, false);
                console.log('Datos del paciente:', response.patient);
                //PINTAMOS LOS DATOS EN EL MODAL DE CITAS
                $('#appointmentModalOpen').modal("show");
                $('#appointmentModalCreate #documento_paciente').val(response.patient.numero_identidad);
                $('#appointmentModalCreate #patient_id').val(response.patient.id);
                $('#appointmentModalCreate #nombre_paciente').val(response.patient.nombre + ' ' + response.patient.apellido_paterno + ' ' + response.patient.apellido_materno);
                
                form.reset();
                $("#patientModalCreate").modal("hide");
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

//PARA EDITAR AL PACIENTE
$(document).on("click", ".edit-patient", async function (e) {
    e.preventDefault();
    let patientId = $(this).data("id");

    try {
        const res = await fetch(`${window.location.origin}/api/patient/show/search`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                id: patientId,
            }),
        });

        const data = await res.json();
        console.log("DATOS PARA EDITAR:", data);

        if (data.message === "encontrado") {
            let p = data.patient;

            //PINTAR DATOS EN EL MODAL
            $("#patientModalEdit #patient_id_edit").val(p.id);
            $("#patientModalEdit #nombre_paciente_edit").val(p.nombre);
            $("#patientModalEdit #apellido_paterno_edit").val(p.apellido_paterno);
            $("#patientModalEdit #apellido_materno_edit").val(p.apellido_materno);
            $("#patientModalEdit #genero_paciente_edit").val(p.genero);
            $("#patientModalEdit #tipo_identificacion_edit").val(p.tipo_identificacion);
            $("#patientModalEdit #numero_identidad_edit").val(p.numero_identidad);
            $("#patientModalEdit #fecha_nacimiento_edit").val(p.fecha_nacimiento);
            $("#patientModalEdit #ocupacion_edit").val(p.ocupacion);
            $("#patientModalEdit #grado_instruccion_edit").val(p.grado_instruccion);
            $("#patientModalEdit #email_edit").val(p.email);
            $("#patientModalEdit #estado_civil_edit").val(p.estado_civil);
            $("#patientModalEdit #telefono_edit").val(p.telefono);
            $("#patientModalEdit #channel_edit").val(p.channel_id);
            $("#patientModalEdit #interaction_medium_edit").val(p.interaction_medium_id);
            $("#patientModalEdit #direccion_edit").val(p.direccion);
            $("#patientModalEdit #familiar_contacto_edit").val(p.familiar_contacto);
            $("#patientModalEdit").modal("show"); //ABRIR MODAL
            //PARA REFREZCAR LOS SELECT
            initSelectEdit();
        }
    } catch (error) {
        console.error(error);
    }
});

//PARA ACTUALIZAR LOS DATOS DEL PACIENTE
$("#formUpdatePatient").on("submit", function (e) {
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
                $("#patientModalEdit").modal("hide");
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

//MOSTRAR FORMULARIO LOS DATOS DEL RESPONSABLE
function responsable(event) {
    console.log(event);

    if (event.target.checked) {
        console.log("vino con su responsable");
        modal_responsable.classList.remove("oculto_card_responsable");
    } else {
        console.log("no vino con su responsable");
        modal_responsable.classList.add("oculto_card_responsable");
    }
}

//PARA BUSCAR PACIENTE FORMULARIO PACIENTE
async function buscarPaciente(event) {
    const valor = event?.target?.value?.trim() || ""; //para desaparacer los datos sin espacio
    if (!valor) return;

    try {
        const res = await fetch(`${window.location.origin}/api/patient/show`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                numero_identidad: valor,
            }),
        });

        const data = await res.json();
        console.log("RESPUESTA DATOS PACIENTE:", data);

        if (data.message === "encontrado" || data.message === 'encontrado_reniec') {
            document.querySelector("#patientModalCreate #nombre_paciente").value = data.patient.nombre;
            document.querySelector("#patientModalCreate #apellido_materno").value = data.patient.apellido_materno;
            document.querySelector("#patientModalCreate #apellido_paterno").value = data.patient.apellido_paterno;
            document.querySelector("#patientModalCreate #fecha_nacimiento").value = data.patient.fecha_nacimiento;
            document.querySelector("#patientModalCreate #ocupacion").value = data.patient.ocupacion;
            document.querySelector("#patientModalCreate #grado_instruccion").value = data.patient.grado_instruccion;
            document.querySelector("#patientModalCreate #telefono").value = data.patient.telefono;
            document.querySelector("#patientModalCreate #channel_id").value = data.patient.channel_id;
            document.querySelector("#patientModalCreate #interaction_medium_id").value = data.patient.interaction_medium_id;
            document.querySelector("#patientModalCreate #email").value = data.patient.email;
            document.querySelector("#patientModalCreate #direccion").value = data.patient.direccion;
            document.querySelector("#patientModalCreate #genero_paciente").value = data.patient.genero;
            document.querySelector("#patientModalCreate #estado_civil").value = data.patient.estado_civil;
            document.querySelector("#patientModalCreate #tipo_identificacion").value = data.patient.tipo_identificacion;

            initSelectCreate();
        }
    } catch (error) {
        console.error("Error:", error);
        console.error("Error al consultar paciente: " + error.message);
    }
}


//PARA INACTIVAR AL PACIENTE
$(document).on("click", ".delete-patient", async function (e) {
    e.preventDefault();

    let patientId = $(this).data("id");

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
        const res = await fetch(`${window.location.origin}/admissionist/patient/delete`, {
            method: "POST",
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                id: patientId,
            }),
        });

        const data = await res.json();
        if (data.code === 1) {
            notificacion("success", "Actualizado", data.msg, 2000, false, true);
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
    $("#tipo_identificacion_edit").selectpicker("refresh");
    $("#genero_paciente_edit").selectpicker("refresh");
    $("#channel_edit").selectpicker("refresh");
    $("#interaction_medium_edit").selectpicker("refresh");
    $("#estado_civil_edit").selectpicker("refresh");
}


//FUNCION PARA PODER INICIAR LOS SELECT
function initSelectCreate() {
    $("#tipo_identificacion").selectpicker("refresh");
    $("#genero_paciente").selectpicker("refresh");
    $("#channel_id").selectpicker("refresh");
    $("#interaction_medium_id").selectpicker("refresh");
    $("#estado_civil").selectpicker("refresh");
    $('#appointmentModalCreate #hora_cita').selectpicker('refresh');
}
