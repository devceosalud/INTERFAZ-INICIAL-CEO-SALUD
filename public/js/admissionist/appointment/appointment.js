//SCRIPT PARA LEER LOS DATOS DEL SELECT
window.addEventListener('DOMContentLoaded', () => {
    console.log('CARGANDDO CITAS');

    //VARIABLES GLOBALES
    const paciente_id = document.querySelector('#appointmentModalCreate #documento_paciente');
    const specialty_id = document.querySelector('#appointmentModalCreate #specialty_id');

    const service_id = document.querySelector('#appointmentModalCreate #service_id');
    const additional_rate_id = document.querySelector('#appointmentModalCreate #additional_rate_id');
    const es_exonerado = document.querySelector('#appointmentModalCreate #es_exonerado');


    //PARA LLENAR DINAMICAMENTE EL PRECIO
    let precio_programado = document.querySelector('#precio_programado');


    //EVENTO PARA BUSCAR AL PACIENTE
    paciente_id.addEventListener('input', function (event) {
        console.log('ESCRIBIENDO EN DOCUMENTO PACIENTE');
        buscarPaciente(event);
    });

    //EVENTO QUE ESPECIALIDAD SELECCIONA 
    specialty_id.addEventListener('change', function (event) {
        buscarEspecialidad(event);
    });

    //EVENTO QUE SERVICIOS SELECCIONA
    service_id.addEventListener('change', function () {
        calcularPrecio();
    });

    additional_rate_id.addEventListener('change', function () {
        calcularPrecio();
    });

    es_exonerado.addEventListener('change', function () {
        calcularPrecio();
    });

    document.querySelector('#total_pagado').addEventListener('input', function () {
        calcularSaldo();
    });

});


//PARA BUSCAR PACIENTE FORMULARIO PACIENTE
async function buscarPaciente(event) {

    const valor = event?.target?.value?.trim() || ''; //para desaparacer los datos sin espacio
    console.log('NUMERO DE DOC: ', valor);

    if (!valor) return;

    try {
        const res = await fetch('http://127.0.0.1:8000/api/patient/show', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                numero_identidad: valor
            }),
        });

        if (!res.ok) {
            const textoError = await res.text();
            throw new Error(`Servidor respondió con código ${res.status}. Revisa el log de Laravel.`);
        }

        const data = await res.json();
        console.log('BUSCAR PACIENTE CITA:', data);

        if (data.message === "encontrado") {
            document.querySelector('#appointmentModalCreate #nombre_paciente').value = data.patient.nombre + ' ' + data.patient.apellido_paterno + ' ' + data.patient.apellido_materno;
            document.querySelector('#appointmentModalCreate #patient_id').value = data.patient.id;
        }

    } catch (error) {
        console.error('Error:', error);
        console.error('Error al consultar paciente: ' + error.message);
    }
}


//BUSCAR ESPECIALIDAD Y LLENAR DOCTORES Y SERVICIOS
async function buscarEspecialidad(event) {

    const valor = event?.target?.value?.trim() || '';
    if (!valor) return;
    console.log('Id de la especialidad:', event.target.value);

    try {
        const res = await fetch('http://127.0.0.1:8000/api/appointment/specialty', {
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
        const selectDoctor = document.getElementById('doctor_id');
        const selectServicio = document.getElementById('service_id');

        //limpiar los campos
        selectDoctor.innerHTML = '<option value="">Seleccione</option>';
        selectServicio.innerHTML = '<option value="">Seleccione</option>';

        //LLENANDO DATOS DE DOCTORES
        data.data.doctors.forEach(doctor => {
            console.log('id', doctor.id);
            console.log('nombre:', doctor.nombre);
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

        $('#doctor_id').selectpicker('destroy');
        $('#service_id').selectpicker('destroy');

        $('#doctor_id').selectpicker();
        $('#service_id').selectpicker();

    } catch (error) {
        console.error('Error:', error);
        console.error('Error al consultar especialidad: ' + error.message);
    }
}


//PARA CALCULAR EL PRECIO
async function calcularPrecio() {

    //ACCEDIENDO A LOS DATOS QUE SE ELIGIO PARA CALCULAR EL PRECIO PARA LA API
    const patient_id = document.querySelector('#appointmentModalCreate #patient_id').value;
    const service_id = document.querySelector('#appointmentModalCreate #service_id').value;
    const additional_rate_id = document.querySelector('#appointmentModalCreate #additional_rate_id').value;
    const es_exonerado = document.querySelector('#appointmentModalCreate #es_exonerado').checked;

    if (!service_id || !patient_id) {
        return;
    }

    try {
        const res = await fetch('http://127.0.0.1:8000/api/appointment/calculated', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                patient_id,
                service_id,
                additional_rate_id,
                es_exonerado
            })
        });

        if (!res.ok) {
            const textoError = await res.text();
            throw new Error(`Servidor respondió con código ${res.status}. Revisa el log de Laravel.`);
        }

        const data = await res.json();
        console.log('Data Precio:', data);

        //ASIGNAMOS LOS DATOS YA CALCULADOS  
        document.querySelector('#precio_programado').value = data.precio_programado;
        document.querySelector('#precio_programado_hidden').value = data.precio_programado;
        if (data.tipo === 'EXONERADO') {
            document.querySelector('#total_pagado').value = data.total_pagado;
        }

        //FUNCION CALCULAR SALDO FINAL
        calcularSaldo();

    } catch (error) {
        console.error('Error:', error);
        console.error('Error al consultar paciente: ' + error.message);
    }
}

//PARA CALCULAR EL SALDO
function calcularSaldo() {

    let precio = parseFloat(document.querySelector('#precio_programado_hidden').value) || 0;
    let pagado = parseFloat(document.querySelector('#total_pagado').value) || 0;

    let saldo = precio - pagado;

    if (saldo < 0) {
        saldo = 0;
    }

    document.querySelector('#saldo_pendiente').value = saldo.toFixed(2);
    document.querySelector('#saldo_pendiente_hidden').value = saldo.toFixed(2);
}


// GUARDAR DATOS DE LA CITA
$('#formCreateAppointment').on('submit', function (e) {
    e.preventDefault();

    let form = this;

    $.ajax({
        url: $(form).attr('action'),
        method: $(form).attr('method'),
        data: new FormData(form),
        processData: false,
        contentType: false,
        dataType: 'json',

        beforeSend: function () {
            // Limpiar errores anteriores
            $(form).find('span.error-text').text('');
            // deshabilitar boton de envio
            $(form).find('input[type="submit"]').prop('disabled', true);
        },

        success: function (response) {
            if (response.code == 0) {
                $.each(response.error, function (prefix, val) {
                    $(form).find('span.' + prefix + '_error').text(val[0]);
                    console.log('span.' + prefix + '_error');
                    console.log(val[0]);
                });
            } else if (response.code == 1) {
                Swal.fire({
                    icon: 'success',
                    title: 'Correcto',
                    text: response.msg,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    //location.reload();
                    window.location.href = "/admissionist/appointment";
                });
                form.reset();
                $('#patientModalCreate').modal('hide');

            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.msg
                });
            }
        },

        error: function (xhr) {
            console.log(xhr.responseText);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Ocurrió un error al guardar el paciente'
            });
        },

        complete: function () {
            $(form).find('input[type="submit"]').prop('disabled', false);
        }
    });
});



