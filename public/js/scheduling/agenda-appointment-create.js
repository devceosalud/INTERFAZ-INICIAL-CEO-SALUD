(function (root, factory) {
    const api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }

    root.AgendaAppointmentCreate = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    function text(value) {
        return value === null || value === undefined ? '' : String(value).trim();
    }

    function payload(selection, patientId, serviceId, responsibleUserId) {
        selection = selection || {};

        if (selection.tipo_contexto !== 'slot_libre' || selection.seleccionable !== true) {
            throw new Error('Seleccione un intervalo disponible.');
        }
        if (!text(patientId)) {
            throw new Error('Seleccione o registre un paciente.');
        }
        if (!text(serviceId)) {
            throw new Error('Selecciona un servicio para agendar la cita.');
        }

        return {
            patient_id: Number(patientId),
            doctor_id: Number(selection.doctor_id),
            service_id: Number(serviceId),
            site_id: selection.site_id === null || selection.site_id === undefined || selection.site_id === ''
                ? null
                : Number(selection.site_id),
            fecha_cita: text(selection.fecha),
            hora_cita: text(selection.hora_inicio),
            duracion_cita: Number(selection.minutos),
            responsible_user_id: text(responsibleUserId) ? Number(responsibleUserId) : null,
        };
    }

    function validationMessage(responsePayload) {
        const errors = responsePayload && responsePayload.errors ? responsePayload.errors : {};
        const field = Object.keys(errors)[0];

        if (field && errors[field] && errors[field][0]) {
            return errors[field][0];
        }

        return responsePayload && responsePayload.message
            ? responsePayload.message
            : 'No se pudo registrar la cita.';
    }

    return {
        payload: payload,
        validationMessage: validationMessage,
    };
}));
