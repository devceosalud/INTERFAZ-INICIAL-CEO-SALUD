(function (root, factory) {
    const api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }

    root.AgendaSelection = api;
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    function valueOrDash(value) {
        return value === null || value === undefined || String(value).trim() === '' ? '—' : String(value);
    }

    function empty(base) {
        base = base || {};

        return {
            mode: 'Sin selección',
            doctor: base.doctor || 'Sin médico disponible',
            specialty: base.specialty || '—',
            date: base.date || '—',
            time: base.time || 'Seleccione un intervalo',
            duration: '—',
            site: base.site || 'Todas las sedes',
            patientId: '',
            patient: 'Sin paciente seleccionado',
            service: 'Pendiente de selección',
            status: 'Sin cita',
            payment: '—',
            clinicalRecord: '—',
            showCompleteRegistration: false,
        };
    }

    function fromContext(context, siteName) {
        const appointment = context.tipo_contexto === 'cita_existente';
        const offHours = context.tipo_contexto === 'fuera_horario';

        return {
            mode: appointment ? 'Cita existente' : (offHours ? 'Fuera de horario' : 'Slot libre'),
            doctor: valueOrDash(context.doctor),
            specialty: context.especialidad || 'Sin especialidad',
            date: valueOrDash(context.fecha),
            time: context.hora_inicio
                ? context.hora_inicio + ' – ' + context.hora_fin
                : 'Resumen diario',
            duration: context.minutos ? context.minutos + ' min' : '—',
            site: siteName,
            patientId: appointment && context.patient_id ? String(context.patient_id) : '',
            patient: appointment ? (context.paciente || 'Paciente sin nombre') : 'Sin paciente seleccionado',
            service: appointment ? (context.servicio || 'Servicio no registrado') : 'Pendiente de selección',
            status: appointment
                ? (context.estado_cita || context.etiqueta || 'Ocupada')
                : (offHours ? 'Fuera de horario' : 'Sin cita'),
            payment: appointment ? valueOrDash(context.estado_pagado) : '—',
            clinicalRecord: appointment ? valueOrDash(context.historia_clinica) : '—',
            showCompleteRegistration: appointment,
        };
    }

    function showDayColumns(view, comparing) {
        return view === 'dia' && !comparing;
    }

    return {
        empty: empty,
        fromContext: fromContext,
        showDayColumns: showDayColumns,
    };
}));
