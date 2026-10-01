(function (root, factory) {
    const api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }

    root.AgendaDayGrid = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    const AVAILABLE = 'DISPONIBLE';
    const OFF_HOURS = 'SIN_HORARIO';

    function minutes(value) {
        const parts = String(value || '00:00').split(':').map(Number);

        return (parts[0] * 60) + (parts[1] || 0);
    }

    function time(value) {
        const bounded = Math.max(0, Math.min(1440, value));
        const hours = Math.floor(bounded / 60);
        const mins = bounded % 60;

        return String(hours).padStart(2, '0') + ':' + String(mins).padStart(2, '0');
    }

    function floor(value, step) {
        return Math.floor(value / step) * step;
    }

    function ceil(value, step) {
        return Math.ceil(value / step) * step;
    }

    function dayFor(professional, date) {
        return (professional && professional.dias || []).find((day) => day.fecha === date) || null;
    }

    function appointmentEvents(events, date) {
        return (events || []).filter((event) => {
            const context = event.extendedProps || {};

            return context.tipo_contexto === 'cita_existente' && context.fecha === date;
        }).map((event) => {
            const context = event.extendedProps;

            return {
                context: context,
                start: minutes(context.hora_inicio),
                end: minutes(context.hora_fin),
                backgroundColor: event.backgroundColor,
                borderColor: event.borderColor,
                textColor: event.textColor,
            };
        }).filter((event) => event.end > event.start)
            .sort((left, right) => left.start - right.start || left.context.appointment_id - right.context.appointment_id);
    }

    function bounds(options) {
        const step = options.gridMinutes || 20;
        const starts = [minutes(options.defaultMin || '07:00')];
        const ends = [minutes(options.defaultMax || '20:00')];
        const day = dayFor(options.professional, options.date);

        (day && day.slots || []).forEach((slot) => {
            starts.push(minutes(slot.inicio));
            ends.push(minutes(slot.fin));
        });
        appointmentEvents(options.events, options.date).forEach((event) => {
            starts.push(event.start);
            ends.push(event.end);
        });

        return {
            start: Math.max(0, floor(Math.min.apply(null, starts), step)),
            end: Math.min(1440, ceil(Math.max.apply(null, ends), step)),
        };
    }

    function covers(slots, start, end, predicate) {
        const intervals = (slots || []).filter(predicate || function () { return true; })
            .map((slot) => ({ start: minutes(slot.inicio), end: minutes(slot.fin) }))
            .filter((slot) => slot.end > start && slot.start < end)
            .sort((left, right) => left.start - right.start);
        let cursor = start;

        for (let index = 0; index < intervals.length; index += 1) {
            const interval = intervals[index];
            if (interval.start > cursor) {
                return false;
            }
            cursor = Math.max(cursor, interval.end);
            if (cursor >= end) {
                return true;
            }
        }

        return false;
    }

    function slotSpan(slot) {
        return {
            slot: slot,
            start: minutes(slot.inicio),
            end: minutes(slot.fin),
        };
    }

    function slotMinutes(slot, rowStart, rowEnd) {
        const span = rowEnd - rowStart;
        const declared = Number(slot && slot.minutos);
        const sameSlot = slot
            && minutes(slot.inicio) === rowStart
            && minutes(slot.fin) === rowEnd;

        if (sameSlot && declared > 0) {
            return declared;
        }

        return span;
    }

    function baseContext(options, rowStart, rowEnd, slot) {
        const professional = options.professional || {};
        const available = Boolean(slot && slot.estado === AVAILABLE);

        return {
            leyenda: available ? AVAILABLE : OFF_HOURS,
            etiqueta: available ? 'Disponible' : 'Fuera de horario',
            doctor_id: professional.id || null,
            doctor: professional.nombre || 'Sin médico disponible',
            especialidad: professional.especialidad || null,
            fecha: options.date,
            hora_inicio: time(rowStart),
            hora_fin: time(rowEnd),
            minutos: slotMinutes(slot, rowStart, rowEnd),
            estado: available ? AVAILABLE : OFF_HOURS,
            site_id: slot ? slot.site_id : null,
            seleccionable: available,
            tipo_contexto: available ? 'slot_libre' : 'fuera_horario',
            appointment_id: null,
            patient_id: null,
            paciente: null,
            servicio: null,
            responsable: null,
        };
    }

    function build(options) {
        const step = options.gridMinutes || 20;
        const range = bounds(options);
        const day = dayFor(options.professional, options.date);
        const slots = (day && day.slots || [])
            .map(slotSpan)
            .filter((span) => span.end > span.start)
            .sort((left, right) => left.start - right.start || left.end - right.end);
        const appointments = appointmentEvents(options.events, options.date);
        const rows = [];

        for (let rowStart = range.start; rowStart < range.end;) {
            const covering = slots.find((span) => span.start <= rowStart && span.end > rowStart);
            const nextSlot = slots.find((span) => span.start > rowStart);
            const stepped = Math.min(range.end, rowStart + step);
            const rowEnd = covering
                ? covering.end
                : (nextSlot ? Math.min(stepped, nextSlot.start) : stepped);

            if (rowEnd <= rowStart) {
                break;
            }

            const starting = appointments.filter((event) => event.start >= rowStart && event.start < rowEnd);
            const continuing = appointments.filter((event) => event.start < rowStart && event.end > rowStart);
            const items = starting.map((event) => Object.assign({ kind: 'appointment' }, event))
                .concat(continuing.map((event) => Object.assign({ kind: 'continuation' }, event)))
                .sort((left, right) => left.start - right.start || (left.kind === 'appointment' ? -1 : 1));
            const slot = covering ? covering.slot : null;
            const available = Boolean(slot && slot.estado === AVAILABLE && covers(
                [slot],
                rowStart,
                rowEnd,
                (candidate) => candidate.estado === AVAILABLE
            ));
            const context = items.length > 0
                ? items[0].context
                : baseContext(options, rowStart, rowEnd, available ? slot : null);

            rows.push({
                id: 'agenda-day-row-' + options.date + '-' + time(rowStart).replace(':', ''),
                start: time(rowStart),
                end: time(rowEnd),
                kind: items.length > 0 ? (starting.length > 0 ? 'appointment' : 'continuation')
                    : (available ? 'available' : 'off-hours'),
                context: context,
                items: items,
            });
            rowStart = rowEnd;
        }

        return { bounds: range, rows: rows };
    }

    return {
        AVAILABLE: AVAILABLE,
        OFF_HOURS: OFF_HOURS,
        build: build,
        bounds: bounds,
        minutes: minutes,
        time: time,
    };
}));
