document.addEventListener('DOMContentLoaded', function () {

    var calendarEl = document.getElementById('calendar');

    window.calendar = new FullCalendar.Calendar(calendarEl, { //window : PARA HACERLO GLOBAL
        initialView: 'dayGridMonth', // timeGridWeek : vista de semana
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
        dayMaxEvents: false, // PARA MOSTRAR O NO LA LSITA COMPLETA DE LAS AGENTAS 


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
                    <td><strong>👤 DR(a):</strong></td>
                    <td>S/. ${info.event.extendedProps.nombre_doctor}</td>
                </tr>
                <tr>
                    <td><strong>💼 Servicio:</strong></td>
                    <td>S/. ${info.event.extendedProps.nombre_servicio}</td>
                </tr>
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

            var clickedDate = info.date; // Obtener la fecha y la hora del clic
            var date = moment(clickedDate).format('YYYY-MM-DD');
            //var dateStr = moment(clickedDate).format('YYYY-MM-DDTHH:mm'); // Formato correcto para datetime-local

            $('#appointmentModalCreate input[name="fecha_cita"]').val(date);
            //$('#appointmentModalCreate input[name="fecha_cita"]').val(info.dateStr);
        },


        //PARA EDITAR LA CITA CUANDO SE DA CLICK EN EL CINTILLO
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

            //limpiar los campos
            selectDoctor.innerHTML = '<option value="">Seleccione</option>';
            selectServicio.innerHTML = '<option value="">Seleccione</option>';

            const option_doctor = document.createElement('option');
            option_doctor.value = doctor_id;
            option_doctor.textContent = nombre_doctor;
            selectDoctor.appendChild(option_doctor);

            const option_servicio = document.createElement('option');
            option_servicio.value = servicio_id;
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

        // PARA PODER CARGAR DINAMICAMENTE Y PASARLE LOS PARAMETROS DE BUSQUEDA
        events: {
            url: '/admissionist/reservation/list-calendar',
            extraParams: function () {
                return {
                    //VARIABLES JALADAS DEL "filtro-calendario.js"
                    specialty_id: document.querySelector('#filtro-calendar_specialty_id').value,
                    doctor_id: document.querySelector('#filtro-calendar_doctor_id').value
                };
            }
        },
    });

    window.calendar.render(); //PARA HACERLO GLOBAL


    //FUNCION QUE SE RESTAURA LOS SELECT
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
