(function (root, factory) {
    const api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }

    root.AgendaWeekBackground = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    const OUTSIDE_BACKGROUND = '#eef1f3';

    function toMinutes(value) {
        const parts = String(value || '').slice(0, 5).split(':').map(Number);

        if (parts.length !== 2 || parts.some(Number.isNaN)) {
            return null;
        }

        return (parts[0] * 60) + parts[1];
    }

    function toTime(minutes) {
        const hours = Math.floor(minutes / 60);
        const remainder = minutes % 60;

        return String(hours).padStart(2, '0') + ':' + String(remainder).padStart(2, '0');
    }

    function merge(ranges) {
        return ranges
            .slice()
            .sort((left, right) => left.start - right.start)
            .reduce((merged, range) => {
                const last = merged[merged.length - 1];

                if (!last || range.start > last.end) {
                    merged.push({ start: range.start, end: range.end });
                } else if (range.end > last.end) {
                    last.end = range.end;
                }

                return merged;
            }, []);
    }

    function outsideEvent(date, start, end) {
        const startTime = toTime(start);
        const endTime = toTime(end);

        return {
            id: 'fuera-horario-' + date + '-' + startTime.replace(':', ''),
            start: date + 'T' + startTime + ':00',
            end: date + 'T' + endTime + ':00',
            display: 'background',
            backgroundColor: OUTSIDE_BACKGROUND,
            borderColor: 'transparent',
            classNames: ['agenda-outside-hours-background'],
            extendedProps: {
                tipo_contexto: 'fuera_horario',
                seleccionable: false,
            },
        };
    }

    function build(professionals, visibleStart, visibleEnd) {
        const start = toMinutes(visibleStart);
        const end = toMinutes(visibleEnd);
        const days = {};

        if (start === null || end === null || end <= start) {
            return [];
        }

        (professionals || []).forEach((professional) => {
            (professional.dias || []).forEach((day) => {
                const date = String(day.fecha || '');

                if (!date) {
                    return;
                }

                days[date] = days[date] || [];
                (day.slots || []).forEach((slot) => {
                    const slotStart = toMinutes(slot.inicio);
                    const slotEnd = toMinutes(slot.fin);

                    if (slotStart === null || slotEnd === null || slotEnd <= slotStart) {
                        return;
                    }

                    const clampedStart = Math.max(start, slotStart);
                    const clampedEnd = Math.min(end, slotEnd);

                    if (clampedEnd > clampedStart) {
                        days[date].push({ start: clampedStart, end: clampedEnd });
                    }
                });
            });
        });

        return Object.keys(days).sort().flatMap((date) => {
            const working = merge(days[date]);
            const outside = [];
            let cursor = start;

            working.forEach((range) => {
                if (range.start > cursor) {
                    outside.push(outsideEvent(date, cursor, range.start));
                }

                cursor = Math.max(cursor, range.end);
            });

            if (cursor < end) {
                outside.push(outsideEvent(date, cursor, end));
            }

            return outside;
        });
    }

    return {
        OUTSIDE_BACKGROUND: OUTSIDE_BACKGROUND,
        build: build,
    };
}));
