//SCRIPT PARA LEER LOS DATOS DEL SELECT
let legacyPatientVersion=0,legacyDoctorVersion=0,legacyServiceVersion=0,legacyHoursVersion=0;
function invalidateLegacyQuote(){
    ++legacyQuoteVersion;legacyQuoteReady=false;
    for(const id of ['precio_programado','precio_programado_hidden','saldo_pendiente','saldo_pendiente_hidden']){
        const field=document.querySelector('#appointmentModalCreate #'+id);if(field)field.value='';
    }
}
window.addEventListener('DOMContentLoaded', () => {
    console.log('CARGANDDO CITAS');

    //VARIABLES GLOBALES
    const paciente_id = document.querySelector('#appointmentModalCreate #documento_paciente');
    const specialty_id = document.querySelector('#appointmentModalCreate #specialty_id');
    const doctor_id = document.querySelector('#appointmentModalCreate #doctor_id');
    const service_id = document.querySelector('#appointmentModalCreate #service_id');
    const additional_rate_id = document.querySelector('#appointmentModalCreate #additional_rate_id');
    const es_exonerado = document.querySelector('#appointmentModalCreate #es_exonerado');

    $('#formCreateAppointment select').on('change', function () {
        if ($.fn.selectpicker) { $(this).selectpicker('refresh'); }
    });

    //PARA LLENAR DINAMICAMENTE EL PRECIO
    let precio_programado = document.querySelector('#precio_programado');


    //EVENTO PARA BUSCAR AL PACIENTE
    paciente_id.addEventListener('input', function (event) {
        console.log('ESCRIBIENDO EN DOCUMENTO PACIENTE');
        buscarPacienteCita(event);
    });

    //EVENTO QUE FILTRA MEDICOS POR LA ESPECIALIDAD
    specialty_id.addEventListener('change', function (event) {
        buscarMedicoPorEspecialidadCita(event);
    });

    //EVENTO QUE FILTAR SERVICIOS POR EL MEDICO
    doctor_id.addEventListener('change', function (event) {
        buscarServicioPorMedicoCita(event);
    })

    //EVENTO QUE SERVICIOS SELECCIONA
    service_id.addEventListener('change', function () {
        calcularPrecioCita();
    });

    //EVENTO PARA CALCUALR EL PRECIO CUANDO SE CAMBIAR UNA TARIFA
    additional_rate_id.addEventListener('change', function () {
        calcularPrecioCita();
    });

    //CHECK DE EXONERADO , VOVELMOS A CALCULAR PRECIO
    es_exonerado.addEventListener('change', function () {
        calcularPrecioCita();
    });

    //SI SE DIGITA UN MONTO DE LA RESERVA , CALCULAMOS EL SALDO FINAL
    document.querySelector('#total_pagado').addEventListener('input', function () {
        calcularSaldoCita();
    });

    //CARGANDO HORARIOS MEDICOS
    $('#appointmentModalCreate #doctor_id').on('change', function () {
        cargarHorariosCita();
    });

    //REFREZCAR LOS HORARIOS DE CITA NORMAL Y DOBLE CITA
    document.querySelector('#appointmentModalCreate #cita_doble').addEventListener('change', function () {
        cargarHorariosCita();
    });

    //REFREZCAR SI HAY CAMBIOS EN LA FECHA
    $('#appointmentModalCreate #fecha_cita').on('change', function () {
        cargarHorariosCita();
        calcularPrecioCita();
    })

});


//PARA BUSCAR PACIENTE FORMULARIO PACIENTE
async function buscarPacienteCita(event) {
    const version=++legacyPatientVersion;
    document.querySelector('#appointmentModalCreate #patient_id').value='';
    document.querySelector('#appointmentModalCreate #nombre_paciente').value='';
    invalidateLegacyQuote();

    const valor = event?.target?.value?.trim() || ''; //para desaparacer los datos sin espacio
    if (!valor) return;

    try {
        const res = await fetch(`${window.location.origin}/api/patient/show`, {
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
        if(version!==legacyPatientVersion || document.querySelector('#appointmentModalCreate #documento_paciente').value.trim()!==valor)return;

        if (data.message === "encontrado") {
            document.querySelector('#appointmentModalCreate #nombre_paciente').value = data.patient.nombre + ' ' + data.patient.apellido_paterno + ' ' + data.patient.apellido_materno;
            document.querySelector('#appointmentModalCreate #patient_id').value = data.patient.id;
            calcularPrecioCita();
        }

    } catch (error) {
        console.error('Error:', error);
        console.error('Error al consultar paciente: ' + error.message);
    }
}


//LLENAMOS LOS DOCTORES CON LA ESPECIALIDAD SELECCIONADA
async function buscarMedicoPorEspecialidadCita(event) {
    const version=++legacyDoctorVersion;++legacyServiceVersion;
    document.querySelector('#appointmentModalCreate #doctor_id').replaceChildren(new Option('Seleccione',''));
    document.querySelector('#appointmentModalCreate #service_id').replaceChildren(new Option('Seleccione',''));
    invalidateLegacyQuote();

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
        if(version!==legacyDoctorVersion || document.querySelector('#appointmentModalCreate #specialty_id').value!==valor)return;
        if (data.code === 0) {
            notificacion("warning", "Precaución", data.message, 4000, false, false);
        }

        const selectDoctor = document.querySelector('#appointmentModalCreate #doctor_id');//SELECT PARA EL LLENADO 
        selectDoctor.innerHTML = '<option value="">Seleccione</option>'; //limpiar los campos

        //LLENANDO DATOS DE DOCTORES
        data.data.doctors.forEach(doctor => {
            const opcion = document.createElement('option');
            opcion.value = doctor.id;
            opcion.textContent = doctor.nombre;
            selectDoctor.appendChild(opcion);
        })

        $('#doctor_id').selectpicker('refresh');

    } catch (error) {
        console.error('Error:', error);
        console.error('Error al consultar especialidad: ' + error.message);
    }
}

//LLENAMOS LOS SERVICIOS POR MEDICO SELECCIONADO
async function buscarServicioPorMedicoCita(event) {
    const version=++legacyServiceVersion;
    document.querySelector('#appointmentModalCreate #service_id').replaceChildren(new Option('Seleccione',''));
    invalidateLegacyQuote();

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
        if(version!==legacyServiceVersion || document.querySelector('#appointmentModalCreate #doctor_id').value!==valor)return;
        if (data.code === 0) {
            notificacion("warning", "Precaución", data.message, 4000, false, false);
        }

        const selectServicio = document.querySelector('#appointmentModalCreate #service_id'); // SELECT PARA EL LLENADO 
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

        $('#service_id').selectpicker('refresh');

    } catch (error) {
        console.error('Error:', error);
        console.error('Error al consultar especialidad: ' + error.message);
    }
}


//PARA CALCULAR EL PRECIO
let legacyQuoteVersion = 0, legacyQuoteReady = false;
async function calcularPrecioCita() {
    const version = ++legacyQuoteVersion, modal = document.getElementById('appointmentModalCreate');
    const field = id => modal.querySelector('#' + id);
    const patient_id=field('patient_id').value, service_id=field('service_id').value;
    legacyQuoteReady=false;
    const status=field('legacy-quote-status');
    if(status) status.textContent='Consultando precio neto vigente…';
    if(!patient_id || !service_id) return;
    const data={patient_id,service_id,additional_rate_id:field('additional_rate_id').value,
        fecha_cita:field('fecha_cita').value || undefined,es_exonerado:field('es_exonerado').checked};
    if(field('reevaluation_indication_id')?.value) data.reevaluation_indication_id=Number(field('reevaluation_indication_id').value);
    if(field('referral_attested')?.checked) data.referral={origin:field('referral_origin').value,attested:true};
    if(field('discount_type')?.value) data.discount={type:field('discount_type').value,value:field('discount_value').value || '0',reason:field('discount_reason').value || null};
    try {
        const res=await fetch(window.location.origin+'/api/appointment/calculated',{method:'POST',
            headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''},
            body:JSON.stringify(data)});
        const result=await res.json();
        if(version!==legacyQuoteVersion)return;
        if(!res.ok)throw Error(result.message || 'No se pudo cotizar.');
        field('precio_programado').value=result.precio_programado;
        field('precio_programado_hidden').value=result.precio_programado;
        legacyQuoteReady=true;
        if(status)status.textContent='Precio neto vigente: S/ '+Number(result.precio_programado).toFixed(2)+'. Confirmar requiere un pago real positivo.';
        calcularSaldoCita();
    } catch(error) {
        if(version!==legacyQuoteVersion)return;
        legacyQuoteReady=false;if(status)status.textContent=error.message;
    }
}
$('#reevaluation_indication_id,#referral_origin').on('input',calcularPrecioCita);
$('#referral_attested').on('change',function(){ $('#referral_origin').prop('disabled',!this.checked);calcularPrecioCita(); });
$('#discount_value').on('input',calcularPrecioCita);
$('#discount_type').on('change',calcularPrecioCita);
$('#fecha_cita').on('change',calcularPrecioCita);

//PARA CALCULAR EL SALDO
function calcularSaldoCita() {
    let precio = parseFloat(document.querySelector('#precio_programado_hidden').value) || 0;
    let pagado = parseFloat(document.querySelector('#total_pagado').value) || 0;
    let saldo = document.querySelector('#appointmentModalCreate #es_exonerado').checked ? 0 : precio - pagado;
    if (saldo < 0) {
        saldo = 0;
    }
    document.querySelector('#saldo_pendiente').value = saldo.toFixed(2);
    document.querySelector('#saldo_pendiente_hidden').value = saldo.toFixed(2);
}


// GUARDAR DATOS DE LA CITA
$('#formCreateAppointment').on('submit', function (e) {
    e.preventDefault();

    if (!legacyQuoteReady) { calcularPrecioCita(); notificacion('warning','Cotización','Espera el precio vigente antes de guardar.',3000,false,false); return; }
    let form = this;
    const body=new FormData(form);
    try {
        if(window.LegacyReceiptReview)window.LegacyReceiptReview.append(body);
        else if(document.getElementById('fileInput')?.files.length)throw new Error('Actualiza el formulario para revisar los comprobantes antes de registrar.');
    }catch(error){notificacion('warning','Comprobantes',error.message,4000,false,false);return;}

    $.ajax({
        url: $(form).attr('action'),
        method: $(form).attr('method'),
        data: body,
        processData: false,
        contentType: false,
        dataType: 'json',

        beforeSend: function () {
            $(form).find('span.error-text').text('');  // Limpiar errores anteriores
            $(form).find('input[type="submit"]').prop('disabled', true); // deshabilitar boton de envio
        },

        success: function (response) {
            let role = document.querySelector('#appointmentModalCreate #rol_user_redirection').value; //VARIABLE ROL DEL USUARIO PARA REDIRECCION


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
                    if (role == 'ADMISION' || role == 'COMERCIAL') {
                        window.location.href = "/admissionist/appointment";
                    } else if (role == 'RECEPCION') {
                        window.location.href = "/receptionist/appointment";
                    } else if(role == 'ADMINISTRADOR') {
                        window.location.href = "/admin/appointment";
                    } else {
                        window.location.href = "/admissionist/appointment";
                    }
                });

            } else if (response.code == 2) {
                notificacion("warning", "Precaución", response.msg, 2000, false, false);
            } else if(response.code == 3) {
                notificacion("warning", "Precaución", response.msg, 2500, false, false);
            } else {
                notificacion("error", "Error", response.msg, 3000, false, false);
            }
        },

        error: function (xhr) {
            const message = xhr.responseJSON && xhr.responseJSON.message
                ? xhr.responseJSON.message : 'No se pudo registrar la cita o el pago. Revisa los datos e intenta nuevamente.';
            notificacion("error", "Pago no registrado", message, 4000, false, false);
        },

        complete: function () {
            $(form).find('input[type="submit"]').prop('disabled', false);
        }
    });
});



//PARA CARGAR HORARIOS
async function cargarHorariosCita() {
    const version=++legacyHoursVersion;

    let doctor_id = $('#appointmentModalCreate #doctor_id').val();
    let fecha_cita = $('#appointmentModalCreate #fecha_cita').val();
    let cita_doble = $('#appointmentModalCreate #cita_doble').is(':checked');

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
        if(version!==legacyHoursVersion || $('#appointmentModalCreate #doctor_id').val()!==doctor_id || $('#appointmentModalCreate #fecha_cita').val()!==fecha_cita || $('#appointmentModalCreate #cita_doble').is(':checked')!==cita_doble)return;
        generarHorariosCita(data.slots, cita_doble);
    } catch (error) {
        console.error(error);
    }
}

// El backend ya resuelve la disponibilidad. Aqui solo se pintan los slots libres.
function generarHorariosCita(slots, cita_doble) {

    let select = document.querySelector("#appointmentModalCreate #hora_cita");
    const field=select;
    if(field.tagName==='INPUT'){
        let list=document.getElementById('legacy-create-available-hours');
        if(!list){list=document.createElement('datalist');list.id='legacy-create-available-hours';field.after(list);}
        field.setAttribute('list',list.id);select=list;select.replaceChildren();
        if(!(slots||[]).some(s=>s.inicio===field.value))field.value='';
    }else select.innerHTML = '<option value="">Seleccione una hora</option>';

    (slots || []).forEach(slot => {
        const opcion = document.createElement('option');
        opcion.value = slot.inicio;
        opcion.textContent = cita_doble ? `${slot.inicio} - ${slot.fin}` : slot.inicio;
        select.appendChild(opcion);
    });

    initSelectCreate();
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
function initSelectCreate() {
    const field=document.querySelector('#appointmentModalCreate #hora_cita');
    if(field.tagName==='SELECT' && $.fn.selectpicker)$(field).selectpicker('refresh');
}
