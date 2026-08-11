//SCRIPT PARA LEER LOS DATOS DEL SELECT
window.addEventListener('DOMContentLoaded', () => {
    console.log('CARGANDDO EDITAR CITAS');

    const specialty_id = document.querySelector('#appointmentModalEdit #specialty_id_edit');
    const doctor_id = document.querySelector('#appointmentModalEdit #doctor_id_edit');


    //CARGANDO HORARIOS MEDICOS
    $('#appointmentModalEdit #doctor_id_edit').on('change', function () {
        cargarHorariosEditarCita();
    });

    //REFREZCAR LOS HORARIOS DE CITA NORMAL Y DOBLE CITA
    document.querySelector('#appointmentModalEdit #cita_doble_edit').addEventListener('change', function () {
        cargarHorariosEditarCita();
    })

    //EVENTO QUE FILTRA MEDICOS POR LA ESPECIALIDAD
    specialty_id.addEventListener('change', function (event) {
        buscarMedicoPorEspecialidadEditarCita(event);
    });

    //EVENTO QUE FILTAR SERVICIOS POR EL MEDICO
    doctor_id.addEventListener('change', function (event) {
        buscarServicioPorMedicoCalendario(event);
    });

});




//PARA ACTUALIZAR LA CITA (REPROGRAMACION)
$("#formUpdateSchedule").on("submit", function (e) {
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
                text: "Ocurrió un error al actualizar el calendario",
            });
        },

        complete: function () {
            $(form).find('input[type="submit"]').prop("disabled", false);
        },
    });
});



//LLENAMOS LOS DOCTORES CON LA ESPECIALIDAD SELECCIONADA
async function buscarMedicoPorEspecialidadEditarCita(event) {

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

        if (!res.ok) {
            const textoError = await res.text();
            throw new Error(`Servidor respondió con código ${res.status}. Revisa el log de Laravel.`);
        }

        const data = await res.json();
        console.log('RESPUESTA LISTA DE DOCTORES', data);

        // SELECT PARA EL LLENADO 
        const selectDoctor = document.querySelector('#appointmentModalEdit #doctor_id_edit');

        //LIMPIAR LOS CAMPOS
        selectDoctor.innerHTML = '<option value="">Seleccione</option>';

        //LLENANDO DATOS DE DOCTORES
        data.data.doctors.forEach(doctor => {
            const opcion = document.createElement('option');
            opcion.value = doctor.id;
            opcion.textContent = doctor.nombre;
            selectDoctor.appendChild(opcion);
        })

        //LLENAR LOS HORARIOS Y PINTARLO EN EL SELECT DE HORARIOS
        initSelectEditarCita();

    } catch (error) {
        console.error('Error:', error);
        console.error('Error al consultar especialidad: ' + error.message);
    }
}

//LLENAMOS LOS SERVICIOS POR MEDICO SELECCIONADO
async function buscarServicioPorMedicoCalendario(event) {

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

        if (!res.ok) {
            const textoError = await res.text();
            throw new Error(`Servidor respondió con código ${res.status}. Revisa el log de Laravel.`);
        }

        const data = await res.json();
        console.log('RESPUESTA LISTA DE SERVICIOS POR DOCTOR', data);

        // SELECT PARA EL LLENADO 
        const selectServicio = document.querySelector('#appointmentModalEdit #service_id_edit');

        //LIMPIAR LOS CAMPOS
        selectServicio.innerHTML = '<option value="">Seleccione</option>';

        //LLENANDO DATOS DE SERVICIOS
        data.data.forEach(service => {
            const opcion = document.createElement('option');
            opcion.value = service.id;
            opcion.textContent = service.nombre + ' - S/' + service.precio_primera_consulta;
            opcion.dataset.precio = service.precio_primera_consulta;
            opcion.dataset.reconsulta = service.precio_reconsulta;
            selectServicio.appendChild(opcion);
        })

        //LLENAR LOS HORARIOS Y PINTARLO EN EL SELECT DE HORARIOS
        initSelectEditarCita();

    } catch (error) {
        console.error('Error:', error);
        console.error('Error al consultar especialidad: ' + error.message);
    }
}


//PARA CARGAR HORARIOS
async function cargarHorariosEditarCita() {

    let doctor_id = $('#appointmentModalEdit #doctor_id_edit').val();
    let fecha_cita = $('#appointmentModalEdit #fecha_cita_edit').val();
    let cita_doble = $('#appointmentModalEdit #cita_doble_edit').is(':checked');

    console.log('doctor:', doctor_id);
    console.log('fecha:', fecha_cita);
    console.log('cita doble:', cita_doble);

    try {
        const res = await fetch(
            `${window.location.origin}/api/appointment/schedule/available-hours`, {
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
        generarHorariosEditarCita(data.horarios, data.ocupadas, cita_doble);
    } catch (error) {
        console.error(error);
    }
}

function generarHorariosEditarCita(horarios, ocupadas, cita_doble) {

    console.log('¿cita doble?', cita_doble);
    let select = document.querySelector("#appointmentModalEdit #hora_cita_edit");
    select.innerHTML = '<option value="">Seleccione una hora</option>';

    horarios.forEach(horario => {
        let inicio = horario.hora_inicio.substring(0, 5);
        let fin = horario.hora_fin.substring(0, 5);
        let duracion = parseInt(horario.duracion_cita);
        let actual = convertirMinutosEditarCita(inicio);
        let final = convertirMinutosEditarCita(fin);

        while (actual < final) {
            let hora = convertirHoraEditarCita(actual);
            // BUSCAR SI ESTA HORA YA ESTÁ OCUPADA
            let horaOcupada = ocupadas.some(cita =>
                cita.hora_cita.substring(0, 5) === hora
            );
            console.log('hora:', hora);
            console.log('¿ocupada?', horaOcupada);

            // CITA NORMAL
            if (!cita_doble) {
                let hayCruce = existeCruceEditarCita(hora, duracion, ocupadas);
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
                    let siguienteHora = convertirHoraEditarCita(siguienteMinuto);
                    let hayCruce = existeCruceEditarCita(hora, duracionDoble, ocupadas);

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
        initSelectEditarCita();
    });
}

function existeCruceEditarCita(horaInicioNueva, duracionNueva, ocupadas) {

    let inicioNueva = convertirMinutosEditarCita(horaInicioNueva);
    let finNueva = inicioNueva + duracionNueva;
    return ocupadas.some(cita => {
        let inicioExistente = convertirMinutosEditarCita(
            cita.hora_cita.substring(0, 5)
        );

        let finExistente = inicioExistente + parseInt(cita.duracion_cita);
        return (inicioNueva < finExistente && finNueva > inicioExistente);
    });
}

function convertirMinutosEditarCita(hora) {
    let partes = hora.split(":");
    console.log('funcion minutos:', partes[0]) * 60 + parseInt(partes[1]);
    return parseInt(partes[0]) * 60 + parseInt(partes[1]);
}

function convertirHoraEditarCita(minutos) {
    let h = Math.floor(minutos / 60);
    let m = minutos % 60;
    h = String(h).padStart(2, '0');
    m = String(m).padStart(2, '0');
    console.log('funcion hora: ', `${h}:${m}`);
    return `${h}:${m}`;
}

function initSelectEditarCita() {
    $('#appointmentModalEdit #hora_cita_edit').selectpicker('destroy');
    $('#appointmentModalEdit #specialty_id_edit').selectpicker('destroy');
    $('#appointmentModalEdit #doctor_id_edit').selectpicker('destroy');
    $('#appointmentModalEdit #service_id_edit').selectpicker('destroy');
    $('#appointmentModalEdit #estado_cita').selectpicker('destroy');

    $('#appointmentModalEdit #hora_cita_edit').selectpicker();
    $('#appointmentModalEdit #specialty_id_edit').selectpicker();
    $('#appointmentModalEdit #doctor_id_edit').selectpicker();
    $('#appointmentModalEdit #service_id_edit').selectpicker();
    $('#appointmentModalEdit #estado_cita').selectpicker();
}