(function () {
    'use strict';
    const byId = id => document.getElementById(id);
    const month = byId('agenda-mini-grid');
    byId('agenda-mini-month').textContent = 'Octubre de 2026';
    for (let i = 0; i < 42; i++) {
        const button = document.createElement('button'); button.type = 'button'; button.className = 'agenda-mini__day';
        const day = new Date(2026, 8, 28 + i); button.textContent = day.getDate();
        button.dataset.capacity = day.getDay() === 5 ? 'verde' : 'plomo'; month.append(button);
    }
    byId('agenda-doctor-filter-summary').textContent = 'Médico de demostración 1';
    const slots = Array.from({ length: 30 }, (_, i) => ({ inicio: window.AgendaDayGrid.time(480 + i * 20),
        fin: window.AgendaDayGrid.time(500 + i * 20), estado: 'DISPONIBLE', minutos: 20 }));
    const model = window.AgendaDayGrid.build({ date: '2026-10-09', professional: { id: 1, dias: [{ fecha: '2026-10-09', slots }] }, events: [] });
    for (const row of model.rows) {
        const line = document.createElement('div'); line.className = 'agenda-day-row agenda-day-row--' + row.kind;
        const entry = document.createElement('div'); entry.className = 'agenda-day-entry'; line.append(entry);
        for (const [i, text] of [row.start, row.kind === 'off-hours' ? 'FH' : '', '', '', ''].entries()) {
            const cell = document.createElement('span'); cell.className = 'agenda-day-cell agenda-day-cell--' + i; cell.textContent = text; entry.append(cell);
        }
        byId('agenda-day-grid-body').append(line);
    }
    byId('agenda-service-select').replaceChildren(new Option('Servicio de demostración', 'demo'));
    // This document never runs agenda.js, the collector or business request handlers.
    document.getElementById('main-wrapper').inert = true;
    document.addEventListener('submit', e => e.preventDefault());
    document.querySelectorAll('a').forEach(el => el.removeAttribute('href'));
    let calendar;
    window.AgendaHeatmapPreview = {
        apply(context, view) {
            byId('preview-audit-link').hidden = !context?.audit_link;
            const mapping = { capture: 'agenda-op-capture-panel', notes: 'agenda-op-notes-panel', payment: 'agenda-op-payment-panel',
                documents: 'agenda-op-documents-panel', workflow: 'agenda-workflow-panel' };
            for (const [key, id] of Object.entries(mapping)) { const el = byId(id); if (el) el.open = (context?.expanded || []).includes(key); }
            byId('agenda-reschedule-form').hidden = !context?.selected;
            byId('agenda-workflow-panel').hidden = !context?.selected;
            byId('agenda-workflow-title').textContent = 'Cita de demostración';
            byId('agenda-board').className = 'agenda-board agenda-board--' + (view === 'dia' ? 'day' : view === 'mes' ? 'month' : 'week');
            if (view !== 'dia') {
                byId('agenda-day-grid').hidden = true; byId('agenda-calendar').hidden = false;
                if (!calendar) {
                    calendar = new window.FullCalendar.Calendar(byId('agenda-calendar'), { initialDate: '2026-10-09',
                        initialView: view === 'mes' ? 'dayGridMonth' : 'timeGridWeek', locale: 'es', firstDay: 1,
                        headerToolbar: false, allDaySlot: false, slotMinTime: '07:00', slotMaxTime: '20:00', height: '100%' });
                    calendar.render();
                } else calendar.changeView(view === 'mes' ? 'dayGridMonth' : 'timeGridWeek');
            } else { byId('agenda-day-grid').hidden = false; byId('agenda-calendar').hidden = true; }
            for (const el of document.querySelectorAll('[data-view]')) {
                el.classList.toggle('is-active', el.dataset.view === view);
            }
            document.querySelector('.agenda-operations').scrollTop = context?.operations_scroll || 0;
            document.querySelector('.agenda-doctor-list').scrollTop = context?.doctors_scroll || 0;
            byId('agenda-day-grid-body').scrollTop = context?.grid_scroll || 0;
            window.scrollTo(0, context?.page_scroll || 0);
        }
    };
}());
