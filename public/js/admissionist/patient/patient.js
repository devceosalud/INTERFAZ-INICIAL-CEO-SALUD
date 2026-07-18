window.addEventListener("DOMContentLoaded", function () {
    console.log('CARGANDO PACIENTES');

    const paciente_id = document.querySelector('#appointmentModalCreate #documento_paciente');
    const input = document.querySelector("#patientModalCreate #formCreatePatient #numero_identidad");
    const responsable_id = document.querySelector("#patientModalCreate #responsable_id");
    const modal_responsable = document.querySelector("#patientModalCreate #modal_responsable");

    const specialty_id = document.querySelector('#appointmentModalCreate #specialty_id');
    const service_id = document.querySelector('#appointmentModalCreate #service_id');
    const additional_rate_id = document.querySelector('#appointmentModalCreate #additional_rate_id');
    const es_exonerado = document.querySelector('#appointmentModalCreate #es_exonerado');

    //EVENTO QUE ESPECIALIDAD SELECCIONA 
    specialty_id.addEventListener('change', function (event) {
        buscarEspecialidad(event);
    });

    //EVENTO QUE SERVICIOS SELECCIONA
    service_id.addEventListener('change', function () {
        calcularPrecio();
    });

    //EVENTO QUE TARIFA SELECCIONA
    additional_rate_id.addEventListener('change', function () {
        calcularPrecio();
    });

    //CHECK ES EXONERADO
    es_exonerado.addEventListener('change', function () {
        calcularPrecio();
    });

    //CUANTO PAGA EL CLIENTE
    document.querySelector('#total_pagado').addEventListener('input', function () {
        calcularSaldo();
    });

    //PARA BUSCAR AL PACIENTE QUE SE DIGITA
    input.addEventListener("input", function (event) {
        buscarPaciente(event);
    });

    //PARA QUE ME MUESTRE EL FORMULARIO
    responsable_id.addEventListener("change", function (event) {
        responsable(event);
    });

    //CARGANDO HORARIOS MEDICOS
    $('#appointmentModalCreate #doctor_id').on('change', function () {
        cargarHorarios();
    });

    $('#appointmentModalCreate #fecha_cita').on('change', function () {
        cargarHorarios();
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
                    //location.reload();
                    //MOSTRAMOS SI QUIERE CREAR CITA O CERRAR
                    $('#appointmentModalOpen').modal("show");
                    console.log('Datos del paciente:', response.patient);
                    //PINTAMOS LOS DATOS EN EL MODAL DE CITAS
                    $('#appointmentModalCreate #documento_paciente').val(response.patient.numero_identidad);
                    $('#appointmentModalCreate #patient_id').val(response.patient.id);
                    $('#appointmentModalCreate #nombre_paciente').val(response.patient.nombre + ' ' + response.patient.apellido_paterno + ' ' + response.patient.apellido_materno);
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
        const res = await fetch(`${window.location.origin}/api/patient/show/search`,
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

//BUSCAR ESPECIALIDAD Y LLENAR DOCTORES Y SERVICIOS
async function buscarEspecialidad(event) {

    const valor = event?.target?.value?.trim() || '';
    if (!valor) return;
    console.log('Id de la especialidad:', event.target.value);

    try {
        const res = await fetch(`${window.location.origin}/api/appointment/specialty`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                specialty_id: valor
            })
        });

        if (!res.ok) {
            const textoError = await res.text();
            throw new Error(`Servidor respondió con código ${res.status}. Revisa el log de Laravel.`);
        }

        const data = await res.json();
        console.log('RESPUESTA ESPECIALIDAD', data);

        // SELECT PARA EL LLENADO 
        const selectDoctor = document.getElementById('doctor_id');
        const selectServicio = document.getElementById('service_id');

        //limpiar los campos
        selectDoctor.innerHTML = '<option value="">Seleccione</option>';
        selectServicio.innerHTML = '<option value="">Seleccione</option>';

        //LLENANDO DATOS DE DOCTORES
        data.data.doctors.forEach(doctor => {
            console.log('id', doctor.id);
            console.log('nombre:', doctor.nombre);
            const opcion = document.createElement('option');
            opcion.value = doctor.id;
            opcion.textContent = doctor.nombre;
            selectDoctor.appendChild(opcion);
        })
        //LLENANDO DATOS DE SERVICIOS
        data.data.services.forEach(service => {
            const opcion = document.createElement('option');
            opcion.value = service.id;
            opcion.textContent = service.nombre + ' - S/' + service.precio_primera_consulta;
            opcion.dataset.precio = service.precio_primera_consulta;
            opcion.dataset.reconsulta = service.precio_reconsulta;
            selectServicio.appendChild(opcion);
        })

        $('#doctor_id').selectpicker('destroy');
        $('#service_id').selectpicker('destroy');

        $('#doctor_id').selectpicker();
        $('#service_id').selectpicker();

    } catch (error) {
        console.error('Error:', error);
        console.error('Error al consultar especialidad: ' + error.message);
    }
}

//PARA CALCULAR EL PRECIO
async function calcularPrecio() {
    console.log('CALCULO DEL PRECIO');

    //ACCEDIENDO A LOS DATOS QUE SE ELIGIO PARA CALCULAR EL PRECIO PARA LA API
    const patient_id = document.querySelector('#appointmentModalCreate #patient_id').value;
    const service_id = document.querySelector('#appointmentModalCreate #service_id').value;
    const additional_rate_id = document.querySelector('#appointmentModalCreate #additional_rate_id').value;
    const es_exonerado = document.querySelector('#appointmentModalCreate #es_exonerado').checked;

    if (!service_id || !patient_id) {return;}

    try {
        const res = await fetch(`${window.location.origin}/api/appointment/calculated`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                patient_id,
                service_id,
                additional_rate_id,
                es_exonerado
            })
        });

        if (!res.ok) {
            const textoError = await res.text();
            throw new Error(`Servidor respondió con código ${res.status}. Revisa el log de Laravel.`);
        }

        const data = await res.json();
        console.log('Data Precio:', data);

        //ASIGNAMOS LOS DATOS YA CALCULADOS  
        document.querySelector('#precio_programado').value = data.precio_programado;
        document.querySelector('#precio_programado_hidden').value = data.precio_programado;
        if (data.tipo === 'EXONERADO') {
            document.querySelector('#total_pagado').value = data.total_pagado;
        }

        //FUNCION CALCULAR SALDO FINAL
        calcularSaldo();

    } catch (error) {
        console.error('Error:', error);
        console.error('Error al consultar paciente: ' + error.message);
    }
}

//PARA CALCULAR EL SALDO
function calcularSaldo() {
    let precio = parseFloat(document.querySelector('#precio_programado_hidden').value) || 0;
    let pagado = parseFloat(document.querySelector('#total_pagado').value) || 0;
    let saldo = precio - pagado;
    if (saldo < 0) {
        saldo = 0;
    }
    document.querySelector('#saldo_pendiente').value = saldo.toFixed(2);
    document.querySelector('#saldo_pendiente_hidden').value = saldo.toFixed(2);
}

// GUARDAR DATOS DE LA CITA
$('#formCreateAppointment').on('submit', function (e) {
    e.preventDefault();

    let form = this;

    $.ajax({
        url: $(form).attr('action'),
        method: $(form).attr('method'),
        data: new FormData(form),
        processData: false,
        contentType: false,
        dataType: 'json',

        beforeSend: function () {
            // Limpiar errores anteriores
            $(form).find('span.error-text').text('');
            // deshabilitar boton de envio
            $(form).find('input[type="submit"]').prop('disabled', true);
        },

        success: function (response) {
            if (response.code == 0) {
                $.each(response.error, function (prefix, val) {
                    $(form).find('span.' + prefix + '_error').text(val[0]);
                    console.log('span.' + prefix + '_error');
                    console.log(val[0]);
                });
            } else if (response.code == 1) {
                Swal.fire({
                    icon: 'success',
                    title: 'Correcto',
                    text: response.msg,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    //location.reload();
                    window.location.href = "/admissionist/appointment";
                });
                form.reset();
                $('#patientModalCreate').modal('hide');

            } else if (response.code == 2) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Precaución',
                    text: response.msg,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                });
            }
            else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.msg
                });
            }
        },

        error: function (xhr) {
            console.log(xhr.responseText);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Ocurrió un error al guardar el paciente'
            });
        },

        complete: function () {
            $(form).find('input[type="submit"]').prop('disabled', false);
        }
    });
});


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


//PARA CARGAR HORARIOS
async function cargarHorarios() {

    let doctor_id = $('#appointmentModalCreate #doctor_id').val();
    let fecha_cita = $('#appointmentModalCreate #fecha_cita').val();
    console.log('fecha', fecha_cita);
    console.log('doctor id:', doctor_id);

    try {
        const res = await fetch(`${window.location.origin}/api/appointment/schedule/available-hours`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    doctor_id: doctor_id,
                    fecha_cita: fecha_cita
                }),
            },
        );

        const data = await res.json();
        console.log("DATOS HORARIOS DOCTOR:", data);
        generarHorarios(data.horarios, data.ocupadas);
    } catch (error) {
        console.error(error);
    }
}

function generarHorarios(horarios, ocupadas) {
    let select = document.querySelector("#appointmentModalCreate #hora_cita");
    select.innerHTML = '<option value="">Seleccione una hora</option>';

    horarios.forEach(horario => {
        console.log('horarios: ', horario);

        let inicio = horario.hora_inicio.substring(0, 5);
        let fin = horario.hora_fin.substring(0, 5);
        let duracion = parseInt(horario.duracion_cita);
        let actual = convertirMinutos(inicio);
        let final = convertirMinutos(fin);

        while (actual < final) {
            let hora = convertirHora(actual);
            console.log('hora:', hora);
            if (!ocupadas.includes(hora + ":00")) {
                const opcion = document.createElement('option');
                opcion.value = hora;
                opcion.textContent = hora;
                select.appendChild(opcion);
            }
            actual += duracion;
        }
        initSelectCreate();
    });
}

function convertirMinutos(hora) {
    let partes = hora.split(":");
    console.log('funcion minutos:', partes[0]) * 60 + parseInt(partes[1]);
    return parseInt(partes[0]) * 60 + parseInt(partes[1]);
}

function convertirHora(minutos) {
    let h = Math.floor(minutos / 60);
    let m = minutos % 60;
    h = String(h).padStart(2, '0');
    m = String(m).padStart(2, '0');
    console.log('funcion hora: ', `${h}:${m}`);
    return `${h}:${m}`;
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
    $('#appointmentModalCreate #hora_cita').selectpicker('destroy');

    $("#tipo_identificacion").selectpicker();
    $("#genero_paciente").selectpicker();
    $("#channel_id").selectpicker();
    $("#interaction_medium_id").selectpicker();
    $("#estado_civil").selectpicker();
    $('#appointmentModalCreate #hora_cita').selectpicker();
}
