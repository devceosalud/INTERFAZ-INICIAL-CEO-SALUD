(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) { module.exports = api; }
    root.AgendaAppointmentActions = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';
    function confirmationText(context, date, time, conversion) {
        const formatted = value => String(value).split('-').reverse().join('/');
        return 'Actual:\n' + formatted(context.fecha) + ' · ' + context.hora_inicio
            + '\n\nNueva fecha:\n' + formatted(date) + ' · ' + time
            + '\n\nPaciente, servicio y precio se conservarán.' + (conversion ? '\n\n' + conversion : '');
    }
    async function reschedule(context, date, time, io) {
        if (!context || !context.appointment_id || !/^\d{4}-\d{2}-\d{2}$/.test(date) || !/^\d{2}:\d{2}$/.test(time)) {
            io.revert();
            throw new Error('Seleccione una cita, fecha y hora válidas.');
        }
        if (!await io.confirm('¿Deseas reprogramar esta cita de ' + context.fecha + ' ' + context.hora_inicio + ' a ' + date + ' ' + time + '?', confirmationText(context, date, time))) {
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
                if (!await io.confirm(result.message, confirmationText(context, date, time, result.message))) { io.revert(); return { cancelled: true }; }
                result = await io.send(context.appointment_id, Object.assign({}, payload, { confirmed_booking_type: result.target_booking_type }));
                if (result && result.confirmation_required) { throw new Error('El destino cambió. Revisa el horario e inténtalo nuevamente.'); }
            }
        }
        catch (error) { io.revert(); throw error; }
        await io.refresh();
        return { cancelled: false, fecha: date, hora: time };
    }
    function reserveIntent(selection, patientId, serviceId) {
        if (!selection || !selection.hora_inicio || selection.tipo_contexto === 'fuera_horario') {
            return { post: false, reason: 'hour' };
        }
        if (!String(patientId || '').trim()) {
            return { post: false, reason: 'patient' };
        }
        if (!String(serviceId || '').trim()) {
            return { post: false, reason: 'service' };
        }
        return { post: true, pending: true, bookingType: 'REGULAR', occupiedHour: selection.tipo_contexto === 'cita_existente' };
    }
    function mondayOf(iso) {
        const parts = String(iso || '').split('-').map(Number);
        const date = new Date(parts[0], parts[1] - 1, parts[2], 12, 0, 0);
        const day = date.getDay();
        date.setDate(date.getDate() + (day === 0 ? -6 : 1 - day));
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    }
    function showsDestination(anchor, view, destination) {
        if (!anchor || !destination) { return false; }
        if (view === 'dia') { return anchor === destination; }
        if (view === 'mes') { return anchor.slice(0, 7) === destination.slice(0, 7); }
        return mondayOf(anchor) === mondayOf(destination);
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
    return { reschedule: reschedule, additionalContext: additionalContext, quickMinute: quickMinute, reserveIntent: reserveIntent, showsDestination: showsDestination, confirmationText: confirmationText };
}));
