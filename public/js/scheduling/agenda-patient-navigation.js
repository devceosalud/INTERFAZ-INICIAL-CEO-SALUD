(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) { module.exports = api; }
    else { root.AgendaPatientNavigation = api; }
}(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict';
    const internalId = value => /^[1-9][0-9]*$/.test(String(value || '')) ? String(value) : '';
    function date(value) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) { return ''; }
        const parsed = new Date(value + 'T12:00:00Z');
        return !isNaN(parsed) && parsed.toISOString().slice(0, 10) === value ? value : '';
    }
    function patientUrl(patientId, context = {}) {
        if (!internalId(patientId)) { throw new Error('Selecciona un paciente para completar su ficha.'); }
        const params = new URLSearchParams({ patient_id: internalId(patientId), from_agenda: '1' });
        if (internalId(context.appointment_id)) { params.set('appointment_id', internalId(context.appointment_id)); }
        if (internalId(context.doctor_id)) { params.set('doctor_id', internalId(context.doctor_id)); }
        if (date(context.fecha)) { params.set('agenda_date', date(context.fecha)); }
        return '/patients?' + params.toString();
    }
    function agendaContext(search) {
        const params = new URLSearchParams(search);
        return { doctor_id: internalId(params.get('doctor_id')), fecha: date(params.get('fecha')),
            appointment_id: internalId(params.get('appointment_id')) };
    }
    return { patientUrl, agendaContext };
}));
