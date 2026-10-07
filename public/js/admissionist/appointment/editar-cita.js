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
$("#formUpdateSchedule").on("submit", function (e) { //formUpdateSchedule
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

        const data = await res.json();
        console.log('RESPUESTA LISTA DE DOCTORES', data);
        if (data.code === 0) {
            notificacion("warning", "Precaución", data.message, 4000, false, false);
        }

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

        const data = await res.json();
        console.log('RESPUESTA LISTA DE SERVICIOS POR DOCTOR', data);
        if (data.code === 0) {
            notificacion("warning", "Precaución", data.message, 4000, false, false);
        }

        // SELECT PARA EL LLENADO 
        const selectServicio = document.querySelector('#appointmentModalEdit #service_id_edit');

        //LIMPIAR LOS CAMPOS
        selectServicio.innerHTML = '<option value="">Seleccione</option>';

        //LLENANDO DATOS DE SERVICIOS
        data.data.forEach(service => {
            const opcion = document.createElement('option');
            opcion.value = service.id;  // cargamos el id de la tabla DoctorServices
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
        const res = await fetch(`${window.location.origin}/api/appointment/schedule/available-hours`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                doctor_id: doctor_id,
                fecha_cita: fecha_cita,
                cita_doble: cita_doble
            }),
        });

        const data = await res.json();
        console.log("DATOS HORARIOS DOCTOR:", data);
        generarHorariosEditarCita(data.slots, cita_doble);
    } catch (error) {
        console.error(error);
    }
}

// El backend ya resuelve la disponibilidad. Aqui solo se pintan los slots libres.
function generarHorariosEditarCita(slots, cita_doble) {

    let select = document.querySelector("#appointmentModalEdit #hora_cita_edit");
    select.innerHTML = '<option value="">Seleccione una hora</option>';

    (slots || []).forEach(slot => {
        const opcion = document.createElement('option');
        opcion.value = slot.inicio;
        opcion.textContent = cita_doble ? `${slot.inicio} - ${slot.fin}` : slot.inicio;
        select.appendChild(opcion);
    });

    initSelectEditarCita();
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

function initSelectEditarCita() {
    $('#appointmentModalEdit #hora_cita_edit').selectpicker('refresh');
    $('#appointmentModalEdit #specialty_id_edit').selectpicker('refresh');
    $('#appointmentModalEdit #doctor_id_edit').selectpicker('refresh');
    $('#appointmentModalEdit #service_id_edit').selectpicker('refresh');
    $('#appointmentModalEdit #estado_cita').selectpicker('refresh');
}