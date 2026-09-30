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
        estado_cita: 'CONFIRMADO',
        estado_pagado: 'PARCIAL',
        historia_clinica: 'HC-100',
    };
}

test('una cita existente carga paciente, pago e historia clínica', () => {
    const state = selection.fromContext(appointment(10, 'PÉREZ GÓMEZ MARÍA'), 'Sede Central');

    assert.equal(state.mode, 'Cita existente');
    assert.equal(state.patientId, '10');
    assert.equal(state.patient, 'PÉREZ GÓMEZ MARÍA');
    assert.equal(state.payment, 'PARCIAL');
    assert.equal(state.clinicalRecord, 'HC-100');
    assert.equal(state.showCompleteRegistration, true);
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
