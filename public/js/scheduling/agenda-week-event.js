(function (root, factory) {
    const api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }

    root.AgendaWeekEvent = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    const SERVICE_MINUTES = 45;

    function build(context) {
        const minutes = Number(context && context.minutos) || 0;
        const start = String(context && context.hora_inicio || '').trim();
        const end = String(context && context.hora_fin || '').trim();
        const patient = String(context && context.paciente || '').trim() || 'Paciente sin nombre';
        const service = String(context && context.servicio || '').trim();
        const showRange = minutes >= SERVICE_MINUTES && end !== '';

        return {
            time: showRange ? start + '–' + end : start,
            start: start,
            end: showRange ? end : '',
            patient: patient,
            service: minutes >= SERVICE_MINUTES ? service : '',
            duration: minutes,
            compact: minutes < 30,
        };
    }

    return {
        SERVICE_MINUTES: SERVICE_MINUTES,
        build: build,
    };
}));
