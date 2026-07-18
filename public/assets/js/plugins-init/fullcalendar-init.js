document.addEventListener('DOMContentLoaded', function () {

    const specialty_id = document.querySelector('#appointmentModalEdit #specialty_id_edit');

    //EVENTO QUE ESPECIALIDAD SELECCIONA 
    specialty_id.addEventListener('change', function (event) {
        buscarEspecialidad(event);
    });


    var calendarEl = document.getElementById('calendar');

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',

        eventDisplay: 'block',

        //seteo de hora
        eventTimeFormat: { // like '14:30:00'
            hour: 'numeric', //2-digit
            minute: '2-digit',
            second: '2-digit',
            meridiem: false
        },

        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },

        editable: true,
        selectable: true,
        businessHours: true,
        dayMaxEvents: false, // para mostrar o no la lista completa de las agendas


        //TOOLTIP PARA VENTANAS RAPIDAS
        eventDidMount: function (info) {

            const contenido = `
        <div class="calendar-popover">
            <div class="border-bottom pb-2 mb-2">
                <h6 class="mb-0 text-primary">
                    👤 ${info.event.title}
                </h6>
                <small class="text-muted">
                    ${info.timeText}
                </small>
            </div>

            <div class="mb-2">
                <span class="badge bg-success">
                    ${info.event.extendedProps.estado_cita}
                </span>
                <span class="badge bg-success">
                    ${info.event.extendedProps.estado_pagado}
                </span>
            </div>

            <table class="display">
                <tr>
                    <td><strong>💰 Pago:</strong></td>
                    <td>S/. ${info.event.extendedProps.total_pagado}</td>
                </tr>
                <tr>
                    <td><strong>💵 Pendiente:</strong></td>
                    <td>S/. ${info.event.extendedProps.saldo_pendiente}</td>
                </tr>
                <tr>
                    <td><strong>📝 Motivo:</strong></td>
                    <td>${info.event.extendedProps.motivo_consulta}</td>
                </tr>
                <tr>
                    <td><strong>📋 Obs:</strong></td>
                    <td>${info.event.extendedProps.observaciones}</td>
                </tr>
            </table>
        </div>
    `;

            new bootstrap.Popover(info.el, {
                html: true,
                trigger: 'hover',
                placement: 'auto',
                sanitize: false,
                container: 'body',
                title: '<strong>Información de la cita</strong>',
                content: contenido
            });

        },

        //PARA REGISTRAR UN EVENTO(MODEL DE AGENDA) EN EL MODAL
        dateClick: function (info) {
            $('#appointmentModalCreate').modal('show');

            // Obtener la fecha y la hora del clic
            var clickedDate = info.date;
            var date = moment(clickedDate).format('YYYY-MM-DD');
            //var dateStr = moment(clickedDate).format('YYYY-MM-DDTHH:mm'); // Formato correcto para datetime-local

            $('#appointmentModalCreate input[name="fecha_cita"]').val(date);
            //$('#appointmentModalCreate input[name="fecha_cita"]').val(info.dateStr);
        },


        //PARA EDITAR LA CITA
        eventClick: function (info) {

            let eventCalendar = info.event; // Objeto de evento de FullCalendar
            let eventComun = info.event.extendedProps; // Propiedades adicionales del evento

            // Acceder a los datos del evento
            let id = eventCalendar.id; // ID del evento
            let title = eventCalendar.title;
            let start = eventCalendar.start; // Formato correcto para datetime-local
            let end = eventCalendar.start; // Usar start si end es null

            // Acceder a los datos extendidos del evento (campos personalizados)
            let documento_paciente = eventComun.documento_paciente;
            let specialty_id = eventComun.specialty_id;
            let nombre_especialidad = eventComun.nombre_especialidad;
            let doctor_id = eventComun.doctor_id;
            let nombre_doctor = eventComun.nombre_doctor;
            let servicio_id = eventComun.service_id;
            let nombre_servicio = eventComun.nombre_servicio;
            let fecha_cita = eventComun.fecha_cita;
            let hora_cita = eventComun.hora_cita;
            let estado_cita = eventComun.estado_cita


            // DATOS COMUNES SETEADOS
            $('#appointmentModalEdit #appointment_id').val(id);
            $('#appointmentModalEdit #documento_paciente_edit').val(documento_paciente);
            $('#appointmentModalEdit #nombre_paciente_edit').val(title);
            $('#appointmentModalEdit #specialty_id_edit').val(specialty_id);


            // SELECT PARA EL LLENADO  DE DOCTORES Y SERVICIOS
            const selectDoctor = document.querySelector('#appointmentModalEdit #doctor_id_edit');
            const selectServicio = document.querySelector('#appointmentModalEdit #service_id_edit');

            const option_doctor = document.createElement('option');
            option_doctor.value = doctor_id;
            option_doctor.textContent = nombre_doctor;
            selectDoctor.appendChild(option_doctor);

            const option_servicio = document.createElement('option');
            option_servicio.value = servicio_id,
                option_servicio.textContent = nombre_servicio;
            selectServicio.appendChild(option_servicio);


            //DATOS DE LA CITA MEDICA
            $('#appointmentModalEdit #fecha_cita_edit').val(fecha_cita);
            $('#appointmentModalEdit #hora_cita_edit').val(hora_cita);
            $('#appointmentModalEdit #estado_cita').val(estado_cita);


            initSelectEdit();

            // Mostrar el modal
            $('#appointmentModalEdit').modal('show');
        },

        events: '/admissionist/reservation/list-calendar',
    });

    calendar.render();

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
            const selectDoctor = document.querySelector('#appointmentModalEdit #doctor_id_edit');
            const selectServicio = document.querySelector('#appointmentModalEdit #service_id_edit');

            //limpiar los campos
            selectDoctor.innerHTML = '<option value="">Seleccione</option>';
            selectServicio.innerHTML = '<option value="">Seleccione</option>';

            //LLENANDO DATOS DE DOCTORES
            data.data.doctors.forEach(doctor => {
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

            //LLENAR LOS HORARIOS Y PINTARLO EN EL SELECT DE HORARIOS

            initSelectEdit();

        } catch (error) {
            console.error('Error:', error);
            console.error('Error al consultar especialidad: ' + error.message);
        }
    }

    function initSelectEdit() {
        $('#appointmentModalEdit #specialty_id_edit').selectpicker('destroy');
        $('#appointmentModalEdit #doctor_id_edit').selectpicker('destroy');
        $('#appointmentModalEdit #service_id_edit').selectpicker('destroy');
        $('#appointmentModalEdit #estado_cita').selectpicker('destroy');

        $('#appointmentModalEdit #specialty_id_edit').selectpicker();
        $('#appointmentModalEdit #doctor_id_edit').selectpicker();
        $('#appointmentModalEdit #service_id_edit').selectpicker();
        $('#appointmentModalEdit #estado_cita').selectpicker();
    }
});
