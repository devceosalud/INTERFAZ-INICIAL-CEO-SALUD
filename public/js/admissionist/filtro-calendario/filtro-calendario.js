//SCRIPT PARA LEER LOS DATOS DEL SELECT
window.addEventListener('DOMContentLoaded', () => {
    console.log('CARGANDO CITAS PARA EL CALENDARIO WEB - NUEVA VERSION');

    //VARIABLES GLOBALES QUE SE COMPARTE AL "calendario.js" DESDE EL FORMULARIO DE FILTRO
    const specialty_id = document.querySelector('#filtro-calendar_specialty_id');
    const doctor_id = document.querySelector('#filtro-calendar_doctor_id');

    //EVENTO QUE ESPECIALIDAD SELECCIONA 
    specialty_id.addEventListener('change', function (event) {
        buscarEspecialidadFiltroCalendario(event);
    });

    //ACTUALIZAR CUANDO SE CAMBIA DE MEDICO
    doctor_id.addEventListener('change', function (event) {
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
        console.log('RESPUESTA LISTA DE DOCTORES FILTRO CALENDARIO', data);
        if (data.code === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Precaución',
                text: data.message,
                timer: 4000,
                showConfirmButton: false
            })
        }

        // SELECT PARA EL LLENADO 
        const selectDoctor = document.querySelector('#filtro-calendar_doctor_id');

        //LIMPIAR LOS CAMPOS
        selectDoctor.innerHTML = '<option value="">Seleccione</option>';

        //LLENANDO DATOS DE DOCTORES PARA EL FILTRO DEL CALENDARIO
        data.data.doctors.forEach(doctor => {
            const opcion = document.createElement('option');
            opcion.value = doctor.id;
            opcion.textContent = doctor.nombre;
            selectDoctor.appendChild(opcion);
        })

        //FUNCION QUE SE RESTAURA LOS SELECT
        $('#filtro-calendar_doctor_id').selectpicker('refresh');

    } catch (error) {
        console.error('Error:', error);
        console.error('Error al consultar especialidad: ' + error.message);
    }
}
