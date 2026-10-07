(function (root, factory) {
    const api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }

    root.AgendaWeekSlots = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    function isFreeSlot(event) {
        const context = event && event.extendedProps ? event.extendedProps : null;

        return Boolean(context
            && context.tipo_contexto === 'slot_libre'
            && context.seleccionable !== false
            && context.hora_inicio
            && context.hora_fin);
    }

    function hits(events) {
        return (events || []).filter(isFreeSlot).map((event) => {
            const context = event.extendedProps;

            return {
                id: 'hit-' + event.id + '-' + context.doctor_id + '-' + String(context.hora_inicio).replace(':', ''),
                start: event.start,
                end: event.end,
                title: '',
                backgroundColor: 'transparent',
                borderColor: 'transparent',
                textColor: 'transparent',
                classNames: ['agenda-slot-hit'],
                extendedProps: context,
            };
        });
    }

    return {
        hits: hits,
    };
}));
