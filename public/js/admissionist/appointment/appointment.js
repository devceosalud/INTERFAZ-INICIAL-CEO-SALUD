//SCRIPT PARA LEER LOS DATOS DEL SELECT
window.addEventListener('DOMContentLoaded', () => {
    console.log('CARGANDDO CITAS');

    //VARIABLES GLOBALES
    const paciente_id = document.querySelector('#appointmentModalCreate #documento_paciente');
    const specialty_id = document.querySelector('#appointmentModalCreate #specialty_id');
    const doctor_id = document.querySelector('#appointmentModalCreate #doctor_id');
    const service_id = document.querySelector('#appointmentModalCreate #service_id');
    const additional_rate_id = document.querySelector('#appointmentModalCreate #additional_rate_id');
    const es_exonerado = document.querySelector('#appointmentModalCreate #es_exonerado');


    //PARA LLENAR DINAMICAMENTE EL PRECIO
    let precio_programado = document.querySelector('#precio_programado');


    //EVENTO PARA BUSCAR AL PACIENTE
    paciente_id.addEventListener('input', function (event) {
        console.log('ESCRIBIENDO EN DOCUMENTO PACIENTE');
        buscarPacienteCita(event);
    });

    //EVENTO QUE FILTRA MEDICOS POR LA ESPECIALIDAD
    specialty_id.addEventListener('change', function (event) {
        buscarMedicoPorEspecialidadCita(event);
    });

    //EVENTO QUE FILTAR SERVICIOS POR EL MEDICO
    doctor_id.addEventListener('change', function (event) {
        buscarServicioPorMedicoCita(event);
    })

    //EVENTO QUE SERVICIOS SELECCIONA
    service_id.addEventListener('change', function () {
        calcularPrecioCita();
    });

    //EVENTO PARA CALCUALR EL PRECIO CUANDO SE CAMBIAR UNA TARIFA
    additional_rate_id.addEventListener('change', function () {
        calcularPrecioCita();
    });

    //CHECK DE EXONERADO , VOVELMOS A CALCULAR PRECIO
    es_exonerado.addEventListener('change', function () {
        calcularPrecioCita();
    });

    //SI SE DIGITA UN MONTO DE LA RESERVA , CALCULAMOS EL SALDO FINAL
    document.querySelector('#total_pagado').addEventListener('input', function () {
        calcularSaldoCita();
    });

    //CARGANDO HORARIOS MEDICOS
    $('#appointmentModalCreate #doctor_id').on('change', function () {
        cargarHorariosCita();
    });

    //REFREZCAR LOS HORARIOS DE CITA NORMAL Y DOBLE CITA
    document.querySelector('#appointmentModalCreate #cita_doble').addEventListener('change', function () {
        cargarHorariosCita();
    })

});


//PARA BUSCAR PACIENTE FORMULARIO PACIENTE
async function buscarPacienteCita(event) {

    const valor = event?.target?.value?.trim() || ''; //para desaparacer los datos sin espacio
    console.log('NUMERO DE DOC: ', valor);
    if (!valor) return;

    try {
        const res = await fetch(`${window.location.origin}/api/patient/show`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                numero_identidad: valor
            }),
        });

        if (!res.ok) {
            const textoError = await res.text();
            throw new Error(`Servidor respondió con código ${res.status}. Revisa el log de Laravel.`);
        }

        const data = await res.json();
        console.log('BUSCAR PACIENTE CITA:', data);

        if (data.message === "encontrado") {
            document.querySelector('#appointmentModalCreate #nombre_paciente').value = data.patient.nombre + ' ' + data.patient.apellido_paterno + ' ' + data.patient.apellido_materno;
            document.querySelector('#appointmentModalCreate #patient_id').value = data.patient.id;
        }

    } catch (error) {
        console.error('Error:', error);
        console.error('Error al consultar paciente: ' + error.message);
    }
}


//LLENAMOS LOS DOCTORES CON LA ESPECIALIDAD SELECCIONADA
async function buscarMedicoPorEspecialidadCita(event) {

    const valor = event?.target?.value?.trim() || '';
    if (!valor) return;
    console.log('Id de la especialidad:', event.target.value);

    try {
        const res = await fetch(`${window.location.origin}/api/appointment/doctor/specialty`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                specialty_id: valor
            })
        });

        const data = await res.json();
        console.log('RESPUESTA LISTA DE DOCTORES', data);
        if (data.code === 0) {
            notificacion("warning", "Precaución", data.message, 4000, false, false);
        }

        // SELECT PARA EL LLENADO 
        const selectDoctor = document.querySelector('#appointmentModalCreate #doctor_id');

        //limpiar los campos
        selectDoctor.innerHTML = '<option value="">Seleccione</option>';

        //LLENANDO DATOS DE DOCTORES
        data.data.doctors.forEach(doctor => {
            const opcion = document.createElement('option');
            opcion.value = doctor.id;
            opcion.textContent = doctor.nombre;
            selectDoctor.appendChild(opcion);
        })

        $('#doctor_id').selectpicker('destroy');
        $('#doctor_id').selectpicker();


    } catch (error) {
        console.error('Error:', error);
        console.error('Error al consultar especialidad: ' + error.message);
    }
}

//LLENAMOS LOS SERVICIOS POR MEDICO SELECCIONADO
async function buscarServicioPorMedicoCita(event) {

    const valor = event?.target?.value?.trim() || '';
    if (!valor) return;
    console.log('Id del medico:', event.target.value);

    try {
        const res = await fetch(`${window.location.origin}/api/appointment/service/doctor`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                doctor_id: valor
            })
        });

        const data = await res.json();
        console.log('RESPUESTA LISTA DE SERVICIOS POR DOCTOR', data);
        if (data.code === 0) {
            notificacion("warning", "Precaución", data.message, 4000, false, false);
        }

        // SELECT PARA EL LLENADO 
        const selectServicio = document.querySelector('#appointmentModalCreate #service_id');

        //LIMPIAR LOS CAMPOS
        selectServicio.innerHTML = '<option value="">Seleccione</option>';

        //LLENANDO DATOS DE SERVICIOS
        data.data.forEach(service => {
            console.log('servicios:', service);
            const opcion = document.createElement('option');
            opcion.value = service.id;  // cargamos el id de la tabla DoctorServices
            opcion.textContent = service.nombre + ' - S/' + service.precio_primera_consulta;
            opcion.dataset.precio = service.precio_primera_consulta;
            opcion.dataset.reconsulta = service.precio_reconsulta;
            selectServicio.appendChild(opcion);
        })

        $('#service_id').selectpicker('destroy');
        $('#service_id').selectpicker();

    } catch (error) {
        console.error('Error:', error);
        console.error('Error al consultar especialidad: ' + error.message);
    }
}


//PARA CALCULAR EL PRECIO
async function calcularPrecioCita() {

    //ACCEDIENDO A LOS DATOS QUE SE ELIGIO PARA CALCULAR EL PRECIO PARA LA API
    const patient_id = document.querySelector('#appointmentModalCreate #patient_id').value;
    const service_id = document.querySelector('#appointmentModalCreate #service_id').value; //id de  pero de la tabla DoctorServices
    const additional_rate_id = document.querySelector('#appointmentModalCreate #additional_rate_id').value;
    const es_exonerado = document.querySelector('#appointmentModalCreate #es_exonerado').checked;

    if (!service_id || !patient_id) {
        return;
    }

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
        calcularSaldoCita();

    } catch (error) {
        console.error('Error:', error);
        console.error('Error al consultar paciente: ' + error.message);
    }
}

//PARA CALCULAR EL SALDO
function calcularSaldoCita() {
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
                notificacion("warning", "Precaución", response.msg, 2000, false, false);
            } else {
                notificacion("error", "Error", response.msg, 3000, false, false);
            }
        },

        error: function (xhr) {
            console.log(xhr.responseText);
            notificacion("error", "Error", xhr.responseText, 4000, false, false);
        },

        complete: function () {
            $(form).find('input[type="submit"]').prop('disabled', false);
        }
    });
});



//PARA CARGAR HORARIOS
async function cargarHorariosCita() {

    let doctor_id = $('#appointmentModalCreate #doctor_id').val();
    let fecha_cita = $('#appointmentModalCreate #fecha_cita').val();
    let cita_doble = $('#appointmentModalCreate #cita_doble').is(':checked');

    console.log('doctor:', doctor_id);
    console.log('fecha:', fecha_cita);
    console.log('cita doble:', cita_doble);

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
        });

        const data = await res.json();
        console.log("DATOS HORARIOS DOCTOR:", data);
        generarHorariosCita(data.horarios, data.ocupadas, cita_doble);
    } catch (error) {
        console.error(error);
    }
}

function generarHorariosCita(horarios, ocupadas, cita_doble) {

    console.log('¿cita doble?', cita_doble);
    let select = document.querySelector("#appointmentModalCreate #hora_cita");
    select.innerHTML = '<option value="">Seleccione una hora</option>';

    horarios.forEach(horario => {
        let inicio = horario.hora_inicio.substring(0, 5);
        let fin = horario.hora_fin.substring(0, 5);
        let duracion = parseInt(horario.duracion_cita);
        let actual = convertirMinutosCita(inicio);
        let final = convertirMinutosCita(fin);

        while (actual < final) {
            let hora = convertirHoraCita(actual);
            // BUSCAR SI ESTA HORA YA ESTÁ OCUPADA
            let horaOcupada = ocupadas.some(cita =>
                cita.hora_cita.substring(0, 5) === hora
            );
            console.log('hora:', hora);
            console.log('¿ocupada?', horaOcupada);

            // CITA NORMAL
            if (!cita_doble) {
                let hayCruce = existeCruceCita(hora, duracion, ocupadas);
                if (!hayCruce) {
                    const opcion = document.createElement('option');
                    opcion.value = hora;
                    opcion.textContent = hora;
                    select.appendChild(opcion);
                }
            }
            // CITA DOBLE
            else {
                let duracionDoble = duracion * 2;
                let siguienteMinuto = actual + duracionDoble;
                // NO SALIR DEL HORARIO DEL MEDICO
                if (siguienteMinuto <= final) {
                    let siguienteHora = convertirHoraCita(siguienteMinuto);
                    let hayCruce = existeCruceCita(hora, duracionDoble, ocupadas);

                    if (!hayCruce) {
                        const opcion = document.createElement('option');
                        opcion.value = hora;
                        opcion.textContent = hora + ' - ' + siguienteHora;
                        select.appendChild(opcion);
                    }
                }
            }
            actual += duracion;
        }
        initSelectCreate();
    });
}

function existeCruceCita(horaInicioNueva, duracionNueva, ocupadas) {

    let inicioNueva = convertirMinutosCita(horaInicioNueva);
    let finNueva = inicioNueva + duracionNueva;
    return ocupadas.some(cita => {
        let inicioExistente = convertirMinutosCita(
            cita.hora_cita.substring(0, 5)
        );

        let finExistente = inicioExistente + parseInt(cita.duracion_cita);
        return (inicioNueva < finExistente && finNueva > inicioExistente);
    });
}

function convertirMinutosCita(hora) {
    let partes = hora.split(":");
    console.log('funcion minutos:', partes[0]) * 60 + parseInt(partes[1]);
    return parseInt(partes[0]) * 60 + parseInt(partes[1]);
}

function convertirHoraCita(minutos) {
    let h = Math.floor(minutos / 60);
    let m = minutos % 60;
    h = String(h).padStart(2, '0');
    m = String(m).padStart(2, '0');
    console.log('funcion hora: ', `${h}:${m}`);
    return `${h}:${m}`;
}


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

//FUNCION QUE SE RESTAURA LOS SELECT
function initSelectCreate() {
    $('#appointmentModalCreate #hora_cita').selectpicker('destroy');

    $('#appointmentModalCreate #hora_cita').selectpicker();
}
