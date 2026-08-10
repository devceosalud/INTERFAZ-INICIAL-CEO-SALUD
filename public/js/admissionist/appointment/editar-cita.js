//SCRIPT PARA LEER LOS DATOS DEL SELECT
window.addEventListener('DOMContentLoaded', () => {
    console.log('CARGANDDO EDITAR CITAS');

    //CARGANDO HORARIOS MEDICOS
    $('#appointmentModalEdit #doctor_id_edit').on('change', function () {
        cargarHorariosEditarCita();
    });

    //REFREZCAR LOS HORARIOS DE CITA NORMAL Y DOBLE CITA
    document.querySelector('#appointmentModalEdit #cita_doble_edit').addEventListener('change', function(){
        cargarHorariosEditarCita();
    })

});



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
                    let hayCruce = existeCruceEditarCita(hora,duracionDoble,ocupadas);

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

    $('#appointmentModalEdit #hora_cita_edit').selectpicker();
}