document.addEventListener('DOMContentLoaded', function () {
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
        dayMaxEvents: true, // allow "more" link when too many events


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
                    <td><strong>💰 Total:</strong></td>
                    <td>S/. ${info.event.extendedProps.total_pagado}</td>
                </tr>
                <tr>
                    <td><strong>💵 Saldo:</strong></td>
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

        events: '/admissionist/reservation/list-calendar',
    });

    calendar.render();
});
