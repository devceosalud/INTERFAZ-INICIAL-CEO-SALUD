(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) { module.exports = api; }
    root.AgendaGuidance = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    const copy = {
        reserveVsSchedule: 'Guardar reserva: guarda el seguimiento sin confirmar el horario. Agendar cita: confirma el horario con al menos 50% de adelanto.',
        insufficientAdvance: 'Para confirmar registra al menos el 50%. Si aún no pagó, usa Guardar reserva.',
        reserveSaved: 'Reserva guardada · Pendiente de adelanto.',
        appointmentSaved: 'Cita agendada.',
        needSelection: 'Selecciona paciente, servicio y horario.',
        needAppointment: 'Primero guarda la reserva o selecciona una cita.',
        needDoctorHour: 'Selecciona una hora del horario del médico.',
        cashNoOperation: 'No se requiere número de operación para efectivo.',
        withdrawalHelp: 'Retiro: el paciente llegó pero se va antes de atenderse. No asistió: el paciente nunca llegó.',
        withdrawalDone: 'Retiro registrado. El horario quedó libre.',
        refundHint: 'Solo se registra la solicitud. El dinero todavía no se devuelve.',
        additional: 'Cita adicional: paciente extra en una hora que ya tiene una cita regular.',
        offHours: 'Fuera de horario: cita excepcional fuera del horario configurado del médico.',
        dniMiss: 'No se encontró el DNI. Completa los datos manualmente.',
        fileInvalid: 'Usa JPG, PNG o PDF de hasta 8 MB.',
        linkInvalid: 'Ingresa un enlace HTTPS válido.',
        permissionGeneric: 'No tienes permiso para esta acción.',
        permissionPayment: 'No tienes permiso para registrar el adelanto.',
        permissionWithdraw: 'No tienes permiso para registrar el retiro.',
        completeChart: 'Completa los datos del paciente. Luego puedes volver a Agenda.',
        resolveNote: 'Indica qué ocurrió: nueva hora, no contesta, no desea reprogramar…',
        legendPrivate: 'Solo la ve su responsable y no ocupa el horario.',
        legendAdditional: 'Paciente extra en una hora con cita regular.',
        legendOffHours: 'Atención excepcional fuera del horario del médico.',
        miniCalendar: 'El color indica cuánto del horario regular ya está confirmado con adelantos.',
    };

    function denied(message, status) {
        const text = String(message || '');
        return Number(status) === 403 || /unauthorized|right permissions|spatie|capability/i.test(text);
    }

    function permission(message, context) {
        const text = String(message || '');
        if (context === 'withdraw' || /retiro/i.test(text)) { return copy.permissionWithdraw; }
        if (context === 'payment' || /adelanto|pago|payment/i.test(text)) { return copy.permissionPayment; }
        return copy.permissionGeneric;
    }

    /** Maps a server or client message to the control that can fix it. */
    function place(message, status, context) {
        const text = String(message || '');
        if (denied(text, status)) {
            const next = permission(text, context);
            const domain = next === copy.permissionWithdraw ? 'withdraw' : (next === copy.permissionPayment ? 'payment' : (context || 'local'));
            return { domain: domain, text: next, focus: false };
        }
        if (/50\s*%|adelanto real de al menos/i.test(text)) {
            return { domain: 'payment', text: copy.insufficientAdvance, focus: true };
        }
        if (/https|links deben|enlace/i.test(text)) {
            return { domain: 'documents', text: copy.linkInvalid, focus: false };
        }
        if (/jpg|png|pdf|8\s*mb|mime|archivo|comprobante/i.test(text)) {
            return { domain: 'documents', text: copy.fileInvalid, focus: false };
        }
        if (/reniec|no se encontr[oó] el dni|obtener datos de reniec/i.test(text)) {
            return { domain: 'identity', text: copy.dniMiss, focus: false };
        }
        if (/n[uú]mero de operaci[oó]n/i.test(text)) {
            return { domain: 'payment', text: text, focus: false };
        }
        return { domain: 'local', text: text || 'No se pudo completar la acción.', focus: false };
    }

    function contingency(patient, when) {
        return 'El horario del médico cambió. La reserva de ' + patient + ' para ' + when + ' se conservó. Contacta al paciente y ofrece otra hora.';
    }

    function invalidProof(file) {
        if (!file) { return false; }
        const allowed = ['image/jpeg', 'image/png', 'application/pdf'];
        return allowed.indexOf(file.type) === -1 || file.size > 8 * 1024 * 1024;
    }

    return { copy: copy, place: place, contingency: contingency, invalidProof: invalidProof, denied: denied };
}));
