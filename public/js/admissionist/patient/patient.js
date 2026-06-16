window.addEventListener("DOMContentLoaded", function () {
    const input = document.querySelector("#numero_identidad");
    const responsable_id = document.querySelector("#responsable_id");
    const modal_responsable = document.querySelector("#modal_responsable");

    input.addEventListener("input", function (event) {
        buscarPaciente(event);
    });

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
                    //MOSTRAMOS EL MODAL PARA AGENDAR CITA O CERRA
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

//PARA EDITAR AL PACIENTE
$(document).on("click", ".edit-patient", async function (e) {
    e.preventDefault();
    let patientId = $(this).data("id");

    try {
        const res = await fetch(
            "http://127.0.0.1:8000/api/patient/show/search",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    id: patientId,
                }),
            },
        );

        const data = await res.json();
        console.log("DATOS PARA EDITAR:", data);

        if (data.message === "encontrado") {
            let p = data.patient;
            //PINTAR DATOS EN EL MODAL
            $("#patient_id_edit").val(p.id);
            $("#nombre_paciente_edit").val(p.nombre);
            $("#apellido_paterno_edit").val(p.apellido_paterno);
            $("#apellido_materno_edit").val(p.apellido_materno);
            $("#genero_paciente_edit").val(p.genero);
            $("#tipo_identificacion_edit").val(p.tipo_identificacion);
            $("#numero_identidad_edit").val(p.numero_identidad);
            $("#fecha_nacimiento_edit").val(p.fecha_nacimiento);
            $("#ocupacion_edit").val(p.ocupacion);
            $("#grado_instruccion_edit").val(p.grado_instruccion);
            $("#email_edit").val(p.email);
            $("#estado_civil_edit").val(p.estado_civil);
            $("#telefono_edit").val(p.telefono);
            $("#channel_edit").val(p.channel_id);
            $("#interaction_medium_edit").val(p.interaction_medium_id);
            $("#direccion_edit").val(p.direccion);
            $("#familiar_contacto_edit").val(p.familiar_contacto);
            //ABRIR MODAL
            $("#patientModalEdit").modal("show");

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

                $("#patientModalEdit").modal("hide");
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
        const res = await fetch("http://127.0.0.1:8000/api/patient/show", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                numero_identidad: valor,
            }),
        });

        const data = await res.json();
        console.log("Respuesta:", data);

        if (data.message === "encontrado") {
            document.querySelector("#nombre_paciente").value = data.patient.nombre;
            document.querySelector("#apellido_materno").value = data.patient.apellido_materno;
            document.querySelector("#apellido_paterno").value = data.patient.apellido_paterno;
            document.querySelector("#fecha_nacimiento").value = data.patient.fecha_nacimiento;
            document.querySelector("#ocupacion").value = data.patient.ocupacion;
            document.querySelector("#grado_instruccion").value = data.patient.grado_instruccion;
            document.querySelector("#telefono").value = data.patient.telefono;
            document.querySelector("#channel_id").value = data.patient.channel_id;
            document.querySelector("#interaction_medium_id").value = data.patient.interaction_medium_id;
            document.querySelector("#email").value = data.patient.email;
            document.querySelector("#direccion").value = data.patient.direccion;
            document.querySelector("#genero_paciente").value = data.patient.genero;
            document.querySelector("#estado_civil").value = data.patient.estado_civil;
            document.querySelector("#tipo_identificacion").value = data.patient.tipo_identificacion;

            initSelectCreate();
        }

        console.log("Respuesta:", data);
    } catch (error) {
        console.error("Error:", error);
        console.error("Error al consultar paciente: " + error.message);
    }
}

//FUNCION PARA PODER INICIAR LOS SELECT
function initSelectEdit() {
    //para campos edit
    $("#tipo_identificacion_edit").selectpicker("destroy");
    $("#genero_paciente_edit").selectpicker("destroy");
    $("#channel_edit").selectpicker("destroy");
    $("#interaction_medium_edit").selectpicker("destroy");
    $("#estado_civil_edit").selectpicker("destroy");

    $("#tipo_identificacion_edit").selectpicker();
    $("#genero_paciente_edit").selectpicker();
    $("#channel_edit").selectpicker();
    $("#interaction_medium_edit").selectpicker();
    $("#estado_civil_edit").selectpicker();
}


function initSelectCreate() {
    $("#tipo_identificacion").selectpicker("destroy");
    $("#genero_paciente").selectpicker("destroy");
    $("#channel_id").selectpicker("destroy");
    $("#interaction_medium_id").selectpicker("destroy");
    $("#estado_civil").selectpicker("destroy");

    $("#tipo_identificacion").selectpicker();
    $("#genero_paciente").selectpicker();
    $("#channel_id").selectpicker();
    $("#interaction_medium_id").selectpicker();
    $("#estado_civil").selectpicker();
}
