(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) { module.exports = api; }
    root.AgendaClickTelemetry = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';
    // Versioned, sanitized module geometry. No DOM text, values, IDs or screenshots.
    const modules = {
        agenda: { label: 'Agenda Operativa', views: ['dia', 'semana', 'mes'], zones: {
            toolbar: [0.02, 0.02, 0.96, 0.10, 'Filtros · Médico · Fecha · Vista'],
            doctors: [0.02, 0.15, 0.20, 0.24, 'Médicos'], mini: [0.24, 0.15, 0.20, 0.24, 'Mini calendario'],
            booking: [0.02, 0.42, 0.42, 0.55, 'Registro rápido · Servicio · Agendar'],
            grid: [0.46, 0.15, 0.52, 0.47, 'Agenda · Grilla horaria'],
            dialog: [0.46, 0.66, 0.52, 0.31, 'Modal · Registrar / editar paciente'] } },
        horarios: { label: 'Horarios Médicos', views: ['horarios'], zones: {
            toolbar: [0.02, 0.02, 0.96, 0.12, 'Médico · Sede · Navegación · Nuevo horario'],
            calendar: [0.02, 0.18, 0.64, 0.79, 'Calendario de horarios'],
            sidebar: [0.69, 0.18, 0.29, 0.32, 'Selección · Grupos · Horarios'],
            dialog: [0.69, 0.54, 0.29, 0.43, 'Formulario de horario'] } },
        pacientes: { label: 'Pacientes', views: ['lista', 'ficha'], zones: {
            toolbar: [0.02, 0.02, 0.96, 0.15, 'Búsqueda · Filtros · Agregar'],
            list: [0.02, 0.21, 0.53, 0.76, 'Listado de pacientes'],
            record: [0.58, 0.21, 0.40, 0.76, 'Ficha · Datos · Guardar / Volver'] } },
    };
    function event(click, width, height, screen, view, zone, rect, uuid, geometry) {
        const module = modules[screen];
        if (!module || !module.views.includes(view) || !module.zones[zone]
            || width < 240 || height < 240 || !rect || rect.width <= 0 || rect.height <= 0) { return null; }
        if (click.target && /^(INPUT|TEXTAREA)$/.test(click.target.tagName || '')) { return null; }
        if (click.target && click.target.isContentEditable) { return null; }
        if (geometry && (click.clientX < rect.left || click.clientX > rect.left + rect.width
            || click.clientY < rect.top || click.clientY > rect.top + rect.height)) { return null; }
        const result = { event_uuid: uuid, screen: screen, layout_version: geometry && screen === 'agenda' ? 3 : 2, zone: zone, view_mode: view,
            element: screen === 'agenda' ? 'agenda.other' : screen + '.control',
            x: Math.max(0, Math.min(1, (click.clientX - rect.left) / rect.width)),
            y: Math.max(0, Math.min(1, (click.clientY - rect.top) / rect.height)),
            viewport_width: Math.min(10000, width), viewport_height: Math.min(10000, height) };
        if (geometry && screen === 'agenda') { result.geometry = geometry; }
        return result;
    }
    function queue(send) {
        let pending = [], busy = false;
        return { add: (item) => { if (item && pending.length < 100) { pending.push(item); } },
            flush: async function () {
                if (busy || !pending.length) { return; }
                busy = true;
                const batch = pending.splice(0, 20);
                try { await send({ events: batch }); } catch (_) { /* Best effort; never block the module. */ }
                finally { busy = false; }
            } };
    }
    return { event: event, queue: queue, modules: modules };
}));

if (typeof document !== 'undefined') { document.addEventListener('DOMContentLoaded', function () {
    const config = document.getElementById('ui-telemetry-config');
    if (!config) { return; }
    const api = window.AgendaClickTelemetry;
    const buffer = api.queue(async function (batch) {
        await fetch(config.dataset.endpoint, { method: 'POST', credentials: 'same-origin', keepalive: true,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(batch) });
    });
    document.addEventListener('click', function (click) {
        try {
            const target = click.target.closest('[data-ui-zone]');
            const surface = target && target.closest('[data-ui-screen]');
            if (!surface || !api.modules[surface.dataset.uiScreen]) { return; }
            const screen = surface.dataset.uiScreen;
            let view = surface.dataset.telemetryView || (screen === 'agenda' ? 'dia' : screen === 'horarios' ? 'horarios' : 'lista');
            if (screen === 'pacientes') { view = document.getElementById('patients-record-surface').hidden ? 'lista' : 'ficha'; }
            if (screen === 'agenda') { view = document.getElementById('agenda-board').dataset.telemetryView || 'dia'; }
            const rect = target.getBoundingClientRect();
            let geometry;
            if (screen === 'agenda') {
                const panels = { capture: 'agenda-op-capture-panel', notes: 'agenda-op-notes-panel', payment: 'agenda-op-payment-panel',
                    documents: 'agenda-op-documents-panel', workflow: 'agenda-workflow-panel' };
                const scroll = selector => Math.round(document.querySelector(selector)?.scrollTop || 0);
                geometry = { left: rect.left, top: rect.top, width: rect.width, height: rect.height,
                    operations_scroll: scroll('.agenda-operations'), doctors_scroll: scroll('.agenda-doctor-list'),
                    grid_scroll: scroll('#agenda-day-grid-body'), page_scroll: Math.round(window.scrollY),
                    expanded: Object.entries(panels).filter(([, id]) => document.getElementById(id)?.open).map(([key]) => key),
                    selected: Boolean(document.getElementById('agenda-reschedule-form') && !document.getElementById('agenda-reschedule-form').hidden),
                    audit_link: Boolean(document.querySelector('.agenda-center__head a')), revision: 1 };
            }
            buffer.add(api.event(click, window.innerWidth, window.innerHeight, screen, view,
                target.dataset.uiZone, rect, window.crypto.randomUUID(), geometry));
        } catch (_) { /* Telemetry must never change the original action. */ }
    }, true);
    window.setInterval(() => buffer.flush(), 5000);
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') { buffer.flush(); } });
    window.addEventListener('pagehide', () => buffer.flush());
}); }
