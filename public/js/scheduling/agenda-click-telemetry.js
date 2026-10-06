(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) { module.exports = api; }
    root.AgendaClickTelemetry = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';
    const zones = {
        'agenda-patient-lookup': 'agenda.patient_search', 'agenda-patient-register': 'agenda.patient_register',
        'agenda-next': 'agenda.next_day', 'agenda-prev': 'agenda.previous_day', 'agenda-today': 'agenda.today',
        'agenda-date': 'agenda.date', 'agenda-doctor-filter': 'agenda.doctor', 'agenda-site': 'agenda.site',
        'agenda-service-select': 'agenda.service', 'agenda-appointment-submit': 'agenda.book',
        'agenda-additional-start': 'agenda.additional', 'agenda-reschedule-form': 'agenda.reschedule',
        'agenda-complete-registration': 'agenda.complete_patient', 'agenda-calendar': 'agenda.calendar',
    };
    function semantic(target) {
        const path = [];
        for (let node = target; node; node = node.parentElement) {
            if (node.dataset && node.dataset.appointmentId) { return 'agenda.appointment'; }
            if (node.classList && node.classList.contains('agenda-calendar-event--appointment')) { return 'agenda.appointment'; }
            if (node.dataset && node.dataset.contextType === 'slot_libre') { return 'agenda.slot'; }
            if (node.classList && node.classList.contains('agenda-slot-hit')) { return 'agenda.slot'; }
            if (node.classList && node.classList.contains('agenda-btn--view')) { return 'agenda.view'; }
            path.push(node.id);
        }
        return path.map((id) => zones[id]).find(Boolean) || 'agenda.other';
    }
    function event(click, width, height, view, uuid) {
        if (width < 240 || height < 240 || !['dia', 'semana', 'mes'].includes(view)) { return null; }
        return {
            event_uuid: uuid, screen: 'agenda', view_mode: view, element: semantic(click.target),
            x: Math.max(0, Math.min(1, click.clientX / width)), y: Math.max(0, Math.min(1, click.clientY / height)),
            viewport_width: Math.min(10000, width), viewport_height: Math.min(10000, height),
        };
    }
    function queue(send) {
        let pending = [], busy = false;
        return {
            add: (item) => { if (item && pending.length < 100) { pending.push(item); } },
            flush: async function () {
                if (busy || !pending.length) { return; }
                busy = true;
                const batch = pending.splice(0, 20);
                try { await send({ events: batch }); } catch (_) { /* Telemetry never controls Agenda. */ }
                finally { busy = false; }
            },
        };
    }
    return { event: event, queue: queue };
}));
