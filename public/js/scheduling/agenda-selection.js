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
            doctor: base.doctor || 'Sin profesional disponible',
            specialty: base.specialty || '—',
            date: base.date || '—',
            time: base.time || 'Seleccione un intervalo',
            duration: '—',
            site: base.site || 'Todas las sedes',
            patientId: '',
            patient: 'Sin paciente seleccionado',
            service: 'Pendiente de selección',
            price: '—',
            commercial: 'Sin asignar',
            creator: null,
            status: 'Sin cita',
            payment: '—',
            clinicalRecord: '—',
            showCompleteRegistration: false,
            schedulable: false,
        };
    }

    function money(value) {
        if (value === null || value === undefined || String(value).trim() === '') {
            return '—';
        }

        const amount = Number(value);

        return Number.isFinite(amount) ? 'S/ ' + amount.toFixed(2) : '—';
    }

    function fromContext(context, siteName) {
        const appointment = context.tipo_contexto === 'cita_existente';
        const offHours = context.tipo_contexto === 'fuera_horario';
        const occupied = context.tipo_contexto === 'slot_ocupado';

        return {
            mode: appointment ? 'Cita existente' : (offHours ? 'Fuera de horario' : (occupied ? 'Intervalo ocupado' : 'Slot libre')),
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
            price: appointment ? money(context.precio_programado) : '—',
            commercial: appointment ? (context.responsable || 'Sin asignar') : 'Sin asignar',
            creator: appointment && context.creador ? context.creador : null,
            status: appointment
                ? (context.estado_cita || context.etiqueta || 'Ocupada')
                : (offHours ? 'Fuera de horario' : (occupied ? 'Ocupado' : 'Sin cita')),
            payment: appointment ? valueOrDash(context.financial_status?.label || context.estado_pagado) : '—',
            clinicalRecord: appointment ? valueOrDash(context.historia_clinica) : '—',
            showCompleteRegistration: appointment,
            schedulable: !appointment && !offHours && !occupied && context.seleccionable === true,
        };
    }

    function registrationDescription(context) {
        if (context.tipo_contexto !== 'cita_existente' || !context.creador) { return ''; }
        return 'Registró: ' + context.creador + ' · Comercial responsable: ' + (context.responsable || 'Sin asignar');
    }

    function showDayColumns(view, comparing) {
        return view === 'dia' && !comparing;
    }

    return {
        empty: empty,
        fromContext: fromContext,
        registrationDescription: registrationDescription,
        showDayColumns: showDayColumns,
    };
}));
