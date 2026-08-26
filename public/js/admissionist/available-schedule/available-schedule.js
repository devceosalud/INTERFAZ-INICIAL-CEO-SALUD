//SCRIPT PARA LEER LOS DATOS DEL SELECT
window.addEventListener('DOMContentLoaded', () => {
    console.log('CARGANDDO HORARIOS DISPONIBLES');

    //VARIABLES GLOBALES
    const specialty_id = document.querySelector('#available-schedule #available-schedule_specialty_id');
    const doctor_id = document.querySelector('#available-schedule #available-schedule_doctor_id');


    //EVENTO QUE FILTRA MEDICOS POR LA ESPECIALIDAD
    specialty_id.addEventListener('change', function (event) {
        buscarMedicoPorEspecialidadCitaDisponibilidad(event);
    });

    //EVENTO QUE FILTAR SERVICIOS POR EL MEDICO
    doctor_id.addEventListener('change', function (event) {
        //buscarServicioPorMedicoCita(event);
        $('#available-schedule #available-schedule_fecha_cita').val('');
    })

    //REFREZCAR LOS HORARIOS DE CITA NORMAL Y DOBLE CITA
    document.querySelector('#available-schedule #cita_doble').addEventListener('change', function () {
        cargarHorariosCitaDisponibilidad();
    });

    //REFREZCAR SI HAY CAMBIOS EN LA FECHA
    $('#available-schedule #available-schedule_fecha_cita').on('change', function () {
        cargarHorariosCitaDisponibilidad();
    })
});


//LLENAMOS LOS DOCTORES CON LA ESPECIALIDAD SELECCIONADA
async function buscarMedicoPorEspecialidadCitaDisponibilidad(event) {

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

        const selectDoctor = document.querySelector('#available-schedule #available-schedule_doctor_id');//SELECT PARA EL LLENADO 
        selectDoctor.innerHTML = '<option value="">Seleccione</option>'; //limpiar los campos

        //LLENANDO DATOS DE DOCTORES
        data.data.doctors.forEach(doctor => {
            const opcion = document.createElement('option');
            opcion.value = doctor.id;
            opcion.textContent = doctor.nombre;
            selectDoctor.appendChild(opcion);
        })

        initSelectDisponibilidad();

    } catch (error) {
        console.error('Error:', error);
        console.error('Error al consultar especialidad: ' + error.message);
    }
}

/*LLENAMOS LOS SERVICIOS POR MEDICO SELECCIONADO
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

        const selectServicio = document.querySelector('#available-schedule #available-schedule_service_id'); // SELECT PARA EL LLENADO 
        selectServicio.innerHTML = '<option value="">Seleccione</option>'; //LIMPIAR LOS CAMPOS

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

        initSelectDisponibilidad();

    } catch (error) {
        console.error('Error:', error);
        console.error('Error al consultar especialidad: ' + error.message);
    }
}*/

//PARA CARGAR HORARIOS
async function cargarHorariosCitaDisponibilidad() {

    let doctor_id = $('#available-schedule #available-schedule_doctor_id').val();
    let fecha_cita = $('#available-schedule #available-schedule_fecha_cita').val();
    let cita_doble = $('#available-schedule #cita_doble').is(':checked');

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
        generarHorariosCitaDisponibilidad(data.horarios, data.ocupadas, cita_doble);
    } catch (error) {
        console.error(error);
    }
}

//ABRIR MODAL DE AGENDA
$(document).on("click", ".open-appointment", function (e) {
    e.preventDefault();

    let hora_escogida = $(this).data("hora");
    let fecha_escogida = $('#available-schedule #available-schedule_fecha_cita').val();

    $("#appointmentModalCreate").modal("show");
    $("#appointmentModalCreate #hora_cita").val(hora_escogida);
    $("#appointmentModalCreate #fecha_cita").val(fecha_escogida);

    console.log('Hora: ' + hora_escogida + "Fecha escogida: " + fecha_escogida);
});

function generarHorariosCitaDisponibilidad(horarios, ocupadas, cita_doble) {

    console.log('¿cita doble?', cita_doble);
    const tbody = document.querySelector('#cuerpo-tabla');
    let html = '';

    horarios.forEach(horario => {
        let inicio = horario.hora_inicio.substring(0, 5);
        let fin = horario.hora_fin.substring(0, 5);
        let duracion = parseInt(horario.duracion_cita);
        let actual = convertirMinutosCitaDisponibilidad(inicio);
        let final = convertirMinutosCitaDisponibilidad(fin);

        while (actual < final) {
            let hora = convertirHoraCitaDisponibilidad(actual);

            if (!cita_doble) { // CITA NORMAL
                let hayCruce = existeCruceCitaDisponibilidad(hora, duracion, ocupadas);
                if (!hayCruce) {
                    html += `
                        <tr>
                            <td class="">
                                <span class="badge light badge-success">Hora: ${hora}</span>
                                <a href="#" class="btn btn-primary btn-sm open-appointment"
                                    data-hora="${hora}">
                                    <i class="fa fa-pencil fs-18 text-success"></i>Escoger
                                </a>
                            </td>
                            <hr>
                        </tr>
                    `;
                }
            } else { // CITA DOBLE
                let duracionDoble = duracion * 2;
                let siguienteMinuto = actual + duracionDoble;
                // NO SALIR DEL HORARIO DEL MEDICO
                if (siguienteMinuto <= final) {
                    let siguienteHora = convertirHoraCitaDisponibilidad(siguienteMinuto);
                    let hayCruce = existeCruceCitaDisponibilidad(hora, duracionDoble, ocupadas);

                    if (!hayCruce) {
                        html += `
                            <tr>
                                <td class="">
                                    <span class="badge light badge-success">Hora: ${hora + ' - ' + siguienteHora}</span>
                                    <a href="#" class="btn btn-primary btn-sm open-appointment"
                                        data-hora="${hora}">
                                        <i class="fa fa-pencil fs-18 text-success"></i>Escoger
                                    </a>
                                </td>
                                <hr>
                            </tr>
                        `;
                    }
                }
            }
            actual += duracion;
        }
    });
    // REGLA DE ORO: Insertar en el DOM una sola vez al terminar todos los ciclos
    tbody.innerHTML = html || '<tr><td colspan="3" class="text-center">No hay horarios disponibles para esta fecha.</td></tr>';
    initSelectDisponibilidad();
}

function existeCruceCitaDisponibilidad(horaInicioNueva, duracionNueva, ocupadas) {

    let inicioNueva = convertirMinutosCitaDisponibilidad(horaInicioNueva);
    let finNueva = inicioNueva + duracionNueva;
    return ocupadas.some(cita => {
        let inicioExistente = convertirMinutosCitaDisponibilidad(
            cita.hora_cita.substring(0, 5)
        );

        let finExistente = inicioExistente + parseInt(cita.duracion_cita);
        return (inicioNueva < finExistente && finNueva > inicioExistente);
    });
}

function convertirMinutosCitaDisponibilidad(hora) {
    let partes = hora.split(":");
    console.log('funcion minutos:', partes[0]) * 60 + parseInt(partes[1]);
    return parseInt(partes[0]) * 60 + parseInt(partes[1]);
}

function convertirHoraCitaDisponibilidad(minutos) {
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
function initSelectDisponibilidad() {
    $('#available-schedule #available-schedule_doctor_id').selectpicker('refresh');
    $('#available-schedule #available-schedule_service_id').selectpicker('refresh');
    $('#available-schedule #hora_cita').selectpicker('refresh');
}
