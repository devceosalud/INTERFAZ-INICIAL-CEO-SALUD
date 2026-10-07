(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) { module.exports = api; }
    root.AgendaGuidance = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    const copy = {
        reserveVsSchedule: 'Guardar reserva: guarda el seguimiento sin confirmar el horario. Agendar cita: confirma el horario con al menos 50% de adelanto.',
        insufficientAdvance: 'Para confirmar registra al menos el 50%. Si aún no pagó, usa Guardar reserva.',
        reserveSaved: 'Reserva guardada. Pendiente de adelanto.',
        reserveSavedHere: 'Reserva guardada para esta hora.',
        appointmentSaved: 'Cita confirmada.',
        additionalSaved: 'Cita adicional creada.',
        offHoursSaved: 'Cita fuera de horario creada.',
        needSelection: 'Selecciona paciente, servicio y horario.',
        needReservePatient: 'Selecciona el paciente de esta reserva.',
        needAppointment: 'Primero guarda la reserva o selecciona una cita.',
        needDoctorHour: 'Selecciona una hora dentro del horario del médico.',
        needAmount: 'Ingresa un monto mayor a S/0.',
        needDocument: 'Selecciona un archivo o agrega un enlace.',
        fileSaved: 'Comprobante adjuntado.',
        linkSaved: 'Enlace agregado.',
        cashNoOperation: 'No se requiere número de operación para efectivo.',
        withdrawalHelp: 'El paciente llegó pero se va antes de atenderse. No asistió significa que nunca llegó.',
        withdrawalDone: 'Retiro registrado. El horario quedó libre.',
        needWithdrawReason: 'Indica el motivo del retiro.',
        needPresence: 'Confirma que el paciente estuvo en la clínica antes de registrar el retiro.',
        needRefundAmount: 'Indica el monto de la solicitud.',
        refundHint: 'Solo se registra la solicitud. El dinero todavía no se devuelve.',
        refundDone: 'Solicitud de devolución registrada.',
        resolved: 'Seguimiento resuelto.',
        additional: 'Cita adicional: paciente extra en una hora que ya tiene una cita regular.',
        offHours: 'Fuera de horario: cita excepcional fuera del horario configurado del médico.',
        dniMiss: 'No se encontró el DNI. Completa los datos manualmente.',
        fileInvalid: 'Usa JPG, PNG o PDF de hasta 8 MB.',
        linkInvalid: 'Ingresa un enlace HTTPS válido.',
        permissionGeneric: 'No tienes permiso para esta acción.',
        permissionPayment: 'No tienes permiso para registrar el adelanto.',
        permissionAdditional: 'No tienes permiso para crear una cita adicional.',
        permissionWithdraw: 'No tienes permiso para registrar el retiro.',
        completeChart: 'Completa los datos del paciente. Luego puedes volver a Agenda.',
        resolveNote: 'Indica qué ocurrió: nueva hora, no contesta, no desea reprogramar…',
        legendPrivate: 'Solo la ve su responsable y no ocupa el horario.',
        legendAdditional: 'Paciente extra en una hora que ya tiene una cita regular.',
        legendOffHours: 'Atención excepcional fuera del horario configurado del médico.',
        miniCalendar: 'El color indica cuánto del horario regular ya está confirmado con adelantos.',
        minutesFree: 'Minutos todavía disponibles dentro del horario del médico.',
        specials: 'Citas adicionales y atenciones fuera de horario.',
    };

    function rescheduled(date, time) {
        return 'Cita reprogramada para ' + date + ' a las ' + time + '.';
    }

    function rebooked(date, time) {
        return 'Nueva cita creada para ' + date + ' a las ' + time + '.';
    }

    function denied(message, status) {
        const text = String(message || '');
        return Number(status) === 403 || /unauthorized|right permissions|spatie|capability/i.test(text);
    }

    function permission(message, context) {
        const text = String(message || '');
        if (context === 'additional') { return copy.permissionAdditional; }
        if (context === 'withdraw' || /retiro/i.test(text)) { return copy.permissionWithdraw; }
        if (context === 'payment' || /adelanto|pago|payment/i.test(text)) { return copy.permissionPayment; }
        return copy.permissionGeneric;
    }

    /** Maps a server or client message to the control that can fix it. */
    function place(message, status, context) {
        const text = String(message || '');
        if (denied(text, status)) {
            if (Number(status) === 403 && text.trim() && !/unauthorized|right permissions|spatie|capability/i.test(text)) {
                return { domain: context === 'withdraw' ? 'withdraw' : 'local', text: text, focus: false };
            }
            const next = permission(text, context);
            const domain = next === copy.permissionWithdraw ? 'withdraw' : (next === copy.permissionPayment ? 'payment' : 'local');
            return { domain: domain, text: next, focus: false };
        }
        if (context === 'withdraw' && /motivo/i.test(text) && /required|obligatorio/i.test(text)) {
            return { domain: 'withdraw', text: copy.needWithdrawReason, focus: false };
        }
        if (context === 'withdraw' && (/was_present|estuvo en la cl[ií]nica|no es no asist/i.test(text))) {
            return { domain: 'withdraw', text: copy.needPresence, focus: false };
        }
        if (context === 'withdraw' && /the (refunds|credits) field is required/i.test(text)) {
            return { domain: 'withdraw', text: copy.needRefundAmount, focus: false };
        }
        if (/mayor a S\/\s*0|monto mayor/i.test(text)) {
            return { domain: 'payment', text: copy.needAmount, focus: true };
        }
        if (/archivo o agrega un enlace/i.test(text)) {
            return { domain: 'documents', text: copy.needDocument, focus: false };
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

    return { copy: copy, place: place, contingency: contingency, invalidProof: invalidProof, denied: denied, rescheduled: rescheduled, rebooked: rebooked };
}));
