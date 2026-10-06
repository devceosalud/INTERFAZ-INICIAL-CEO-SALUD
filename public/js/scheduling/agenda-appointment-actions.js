(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) { module.exports = api; }
    root.AgendaAppointmentActions = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';
    async function reschedule(context, date, time, io) {
        if (!context || !context.appointment_id || !/^\d{4}-\d{2}-\d{2}$/.test(date) || !/^\d{2}:\d{2}$/.test(time)) {
            io.revert();
            throw new Error('Seleccione una cita, fecha y hora válidas.');
        }
        if (!io.confirm('¿Deseas reprogramar esta cita de ' + context.fecha + ' ' + context.hora_inicio + ' a ' + date + ' ' + time + '?')) {
            io.revert();
            return { cancelled: true };
        }
        const payload = {
            fecha_cita: date, hora_cita: time, expected_fecha_cita: context.fecha, expected_hora_cita: context.hora_inicio,
        };
        try {
            let result = await io.send(context.appointment_id, payload);
            if (result && result.confirmation_required) {
                if (!['REGULAR', 'FUERA_HORARIO'].includes(result.target_booking_type)) { throw new Error('Clasificación de destino inválida.'); }
                if (!io.confirm(result.message)) { io.revert(); return { cancelled: true }; }
                result = await io.send(context.appointment_id, Object.assign({}, payload, { confirmed_booking_type: result.target_booking_type }));
                if (result && result.confirmation_required) { throw new Error('El destino cambió. Revisa el horario e inténtalo nuevamente.'); }
            }
        }
        catch (error) { io.revert(); throw error; }
        await io.refresh();
        return { cancelled: false };
    }
    function additionalContext(context, slot) {
        if (!context || !slot || slot.inicio !== context.hora_inicio || !(Number(slot.minutos) > 0)) {
            throw new Error('Seleccione un intervalo real del horario médico.');
        }
        return {
            tipo_contexto: 'slot_libre', seleccionable: true, leyenda: 'ADICIONAL', etiqueta: 'ADICIONAL',
            estado: 'DISPONIBLE', doctor_id: context.doctor_id, doctor: context.doctor,
            especialidad: context.especialidad, fecha: context.fecha, site_id: slot.site_id,
            hora_inicio: slot.inicio, hora_fin: slot.fin, minutos: Number(slot.minutos),
            appointment_id: null, patient_id: null, paciente: null, responsable: null,
        };
    }
    function quickMinute(value, minute) {
        const match = /^([01]?\d|2[0-3])(?::[0-5]\d)?$/.exec(String(value).trim());
        return match && ['00', '20', '40'].includes(minute) ? match[1].padStart(2, '0') + ':' + minute : null;
    }
    return { reschedule: reschedule, additionalContext: additionalContext, quickMinute: quickMinute };
}));
