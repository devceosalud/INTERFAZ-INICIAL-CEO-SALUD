document.addEventListener('DOMContentLoaded', function () {

    var calendarEl = document.getElementById('calendar-medico');
    // Obtener la fecha actual en formato ISO (YYYY-MM-DD)
    //const hoy = new Date().toISOString().split('T')[0];

    window.calendar_medico = new FullCalendar.Calendar(calendarEl, { //window : PARA HACERLO GLOBAL
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

        editable: false,
        selectable: true,
        businessHours: true,
        dayMaxEvents: false, // PARA MOSTRAR O NO LA LSITA COMPLETA DE LAS AGENTAS 

        //validRange: {
        //    start: hoy // Bloquea la selección y navegación visual antes de hoy
        //},

        //PARA REGISTRAR UN EVENTO(MODEL DE AGENDA) EN EL MODAL
        dateClick: function (info) {
            $('#doctorScheduleModalCreate').modal('show');

            var clickedDate = info.date; // Obtener la fecha y la hora del clic
            var date = moment(clickedDate).format('YYYY-MM-DD');
            //var dateStr = moment(clickedDate).format('YYYY-MM-DDTHH:mm'); // Formato correcto para datetime-local

            $('#doctorScheduleModalCreate input[name="fecha_cita"]').val(date);
            //$('#appointmentModalCreate input[name="fecha_cita"]').val(info.dateStr);
        },


        //PARA EDITAR LA CITA CUANDO SE DA CLICK EN EL CINTILLO
        eventClick: function (info) {

            let eventCalendar = info.event; // Objeto de evento de FullCalendar
            let eventComun = info.event.extendedProps; // Propiedades adicionales del evento

            console.log('eventCalendar:', eventCalendar);
            console.log('eventComun', eventComun);

            //DATOS DE LA CITA MEDICA
            $('#doctorScheduleModalEdit #doctor_schedule_id_edit').val(eventComun.doctor_schedule_id_edit);
            $('#doctorScheduleModalEdit #doctor_id_edit').val(eventComun.doctor_id_edit);
            $('#doctorScheduleModalEdit #hora_inicio_edit').val(eventComun.hora_inicio_edit);
            $('#doctorScheduleModalEdit #hora_fin_edit').val(eventComun.hora_fin_edit);
            $('#doctorScheduleModalEdit #duracion_edit_cita').val(eventComun.duracion_edit_cita);
            $('#doctorScheduleModalEdit #fecha_cita_edit').val(eventComun.fecha_cita_edit);
            $("#doctorScheduleModalEdit").modal("show");//ABRIR MODAL

            initSelectEdit();
        },

        /*events: {
            url: '/admissionist/doctor-schedule/calendar'
        }*/

        // PARA PODER CARGAR DINAMICAMENTE Y PASARLE LOS PARAMETROS DE BUSQUEDA
        events: {
            url: '/admissionist/doctor-schedule/calendar',
            extraParams: function () {
                return {
                    //VARIABLES JALADAS DEL "filtro-calendario.js"
                    //specialty_id: document.querySelector('#filtro-calendar-medico_specialty_id').value,
                    doctor_id: document.querySelector('#filtro-calendar-medico_doctor_id').value,
                };
            }
        }, 
    });

    window.calendar_medico.render(); //PARA HACERLO GLOBAL


    //FUNCION PARA PODER INICIAR LOS SELECT
    function initSelectEdit() {
        //para campos edit
        $("#doctorScheduleModalEdit #doctor_id_edit").selectpicker("refresh");
        $("#doctorScheduleModalEdit #dia_semana_edit").selectpicker("refresh");
        $("#doctorScheduleModalEdit #duracion_edit_cita").selectpicker("refresh");
    }
});
