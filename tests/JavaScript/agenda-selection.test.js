'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const selection = require('../../public/js/scheduling/agenda-selection.js');

function appointment(id, name) {
    return {
        tipo_contexto: 'cita_existente',
        patient_id: id,
        paciente: name,
        doctor: 'Dra. Operativa',
        especialidad: 'Cardiología',
        fecha: '2026-10-05',
        hora_inicio: '09:15',
        hora_fin: '09:45',
        minutos: 30,
        servicio: 'Consulta',
        service_id: 3,
        precio_programado: 150,
        responsable: 'Demo Comercial Local',
        responsible_user_id: 11,
        creador: 'Demo Admision Local',
        creator_user_id: 10,
        estado_cita: 'CONFIRMADO',
        estado_pagado: 'PARCIAL',
        historia_clinica: 'HC-100',
        seleccionable: false,
    };
}

test('una cita existente carga paciente, pago e historia clínica', () => {
    const state = selection.fromContext(appointment(10, 'PÉREZ GÓMEZ MARÍA'), 'Sede Central');

    assert.equal(state.mode, 'Cita existente');
    assert.equal(state.patientId, '10');
    assert.equal(state.patient, 'PÉREZ GÓMEZ MARÍA');
    assert.equal(state.payment, 'PARCIAL');
    assert.equal(state.clinicalRecord, 'HC-100');
    assert.equal(state.service, 'Consulta');
    assert.equal(state.price, 'S/ 150.00');
    assert.equal(state.commercial, 'Demo Comercial Local');
    assert.equal(state.creator, 'Demo Admision Local');
    assert.equal(state.status, 'CONFIRMADO');
    assert.equal(state.payment, 'PARCIAL');
    assert.equal(state.showCompleteRegistration, true);
    assert.equal(state.schedulable, false);
});

test('el precio mostrado es el persistido y un slot ocupado no se agenda', () => {
    const context = appointment(12, 'PACIENTE HISTORICO');
    context.servicio = 'PIE DIABETICO';
    context.precio_programado = 150;
    context.estado_cita = 'PROGRAMADO';
    context.estado_pagado = 'PENDIENTE';

    const state = selection.fromContext(context, 'Todas las sedes');

    assert.equal(state.service, 'PIE DIABETICO');
    assert.equal(state.price, 'S/ 150.00');
    assert.equal(state.status, 'PROGRAMADO');
    assert.equal(state.payment, 'PENDIENTE');
    assert.equal(state.schedulable, false);
});

test('un slot libre sigue sin precio historico y puede agendarse', () => {
    const state = selection.fromContext({
        tipo_contexto: 'slot_libre',
        seleccionable: true,
        doctor: 'Dra. Operativa',
        fecha: '2026-10-05',
        hora_inicio: '10:00',
        hora_fin: '10:20',
        minutos: 20,
        servicio: 'NO USAR',
        precio_programado: 999,
        responsable: 'NO USAR',
        creador: 'NO USAR',
    }, 'Sede Central');

    assert.equal(state.service, 'Pendiente de selección');
    assert.equal(state.price, '—');
    assert.equal(state.commercial, 'Sin asignar');
    assert.equal(state.creator, null);
    assert.equal(state.schedulable, true);
});

test('cambiar de cita reemplaza por completo el contexto anterior', () => {
    const first = selection.fromContext(appointment(10, 'PACIENTE A'), 'Sede Central');
    const second = selection.fromContext(appointment(20, 'PACIENTE B'), 'Sede Central');

    assert.notEqual(first.patientId, second.patientId);
    assert.equal(second.patientId, '20');
    assert.equal(second.patient, 'PACIENTE B');
});

test('cambiar de cita a slot libre limpia paciente y datos financieros', () => {
    const slot = selection.fromContext({
        tipo_contexto: 'slot_libre',
        doctor: 'Dra. Operativa',
        especialidad: 'Cardiología',
        fecha: '2026-10-05',
        hora_inicio: '10:00',
        hora_fin: '10:20',
        minutos: 20,
    }, 'Sede Central');

    assert.equal(slot.mode, 'Slot libre');
    assert.equal(slot.patientId, '');
    assert.equal(slot.patient, 'Sin paciente seleccionado');
    assert.equal(slot.payment, '—');
    assert.equal(slot.clinicalRecord, '—');
    assert.equal(slot.showCompleteRegistration, false);
});

test('una cita sin historia clínica identificada presenta un guion y no inventa H.C.', () => {
    const context = appointment(30, 'PACIENTE SIN H.C.');
    context.historia_clinica = null;

    assert.equal(selection.fromContext(context, 'Sede Central').clinicalRecord, '—');
});

test('la cabecera operacional aparece solo en Día sin comparación', () => {
    assert.equal(selection.showDayColumns('dia', false), true);
    assert.equal(selection.showDayColumns('dia', true), false);
    assert.equal(selection.showDayColumns('semana', false), false);
    assert.equal(selection.showDayColumns('mes', false), false);
});

test('fuera de horario limpia paciente y no se presenta como agendable', () => {
    const state = selection.fromContext({
        tipo_contexto: 'fuera_horario',
        doctor: 'Dra. Ana Quispe',
        especialidad: 'Medicina física',
        fecha: '2026-09-29',
        hora_inicio: '07:20',
        hora_fin: '07:40',
        minutos: 20,
        paciente: 'NO DEBE QUEDAR',
        patient_id: 99,
        estado_pagado: 'PAGADO',
        historia_clinica: 'HC99',
    }, 'Sede Central');

    assert.equal(state.mode, 'Fuera de horario');
    assert.equal(state.status, 'Fuera de horario');
    assert.equal(state.patientId, '');
    assert.equal(state.patient, 'Sin paciente seleccionado');
    assert.equal(state.payment, '—');
    assert.equal(state.clinicalRecord, '—');
    assert.equal(state.showCompleteRegistration, false);
});
