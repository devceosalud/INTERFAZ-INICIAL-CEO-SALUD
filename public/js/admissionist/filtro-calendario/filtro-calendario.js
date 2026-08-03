//SCRIPT PARA LEER LOS DATOS DEL SELECT
window.addEventListener('DOMContentLoaded', () => {
    console.log('CARGANDO CITAS PARA EL CALENDARIO WEB - NUEVA VERSION');

    //VARIABLES GLOBALES QUE SE COMPARTE AL "calendario.js"
    const specialty_id = document.querySelector('#filtro-calendar_specialty_id');
    const doctor_id = document.querySelector('#filtro-calendar_doctor_id');

    //EVENTO QUE ESPECIALIDAD SELECCIONA 
    specialty_id.addEventListener('change', function (event) {
        buscarEspecialidadFiltroCalendario(event);
    });

    //ACTUALIZAR CUANDO SE CAMBIA DE MEDICO
    doctor_id.addEventListener('change', function(event) {
        const doctorId = event.target.value;
        console.log('MEDICO ID - FILTRO CALENDARIO', doctorId);

        //RECARGAR CALENDARIO CON EL window => LE HACEMOS GLOBAL
        window.calendar.refetchEvents();
    })
});



//BUSCAR ESPECIALIDAD Y LLENAR DOCTORES Y SERVICIOS
async function buscarEspecialidadFiltroCalendario(event) {

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
        const selectDoctor = document.querySelector('#filtro-calendar_doctor_id');
        const selectServicio = document.querySelector('#appointmentModalCreate #service_id');

        //limpiar los campos
        selectDoctor.innerHTML = '<option value="">Seleccione</option>';
        selectServicio.innerHTML = '<option value="">Seleccione</option>';

        //LLENANDO DATOS DE DOCTORES PARA EL FILTRO DEL CALENDARIO
        data.data.doctors.forEach(doctor => {
            console.log('id', doctor.id);
            console.log('nombre:', doctor.nombre);
            const opcion = document.createElement('option');
            opcion.value = doctor.id;
            opcion.textContent = doctor.nombre;
            selectDoctor.appendChild(opcion);
        })

        //LLENANDO DATOS DE SERVICIOS PARA EL FILTRO DEL CALENDARIO
        data.data.services.forEach(service => {
            const opcion = document.createElement('option');
            opcion.value = service.id;
            opcion.textContent = service.nombre + ' - S/' + service.precio_primera_consulta;
            opcion.dataset.precio = service.precio_primera_consulta;
            opcion.dataset.reconsulta = service.precio_reconsulta;
            selectServicio.appendChild(opcion);
        })

        $('#filtro-calendar_doctor_id').selectpicker('destroy');
        $('#service_id').selectpicker('destroy');

        $('#filtro-calendar_doctor_id').selectpicker();
        $('#service_id').selectpicker();

    } catch (error) {
        console.error('Error:', error);
        console.error('Error al consultar especialidad: ' + error.message);
    }
}
