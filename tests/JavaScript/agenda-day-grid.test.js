'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const grid = require('../../public/js/scheduling/agenda-day-grid.js');
const selection = require('../../public/js/scheduling/agenda-selection.js');

const date = '2026-09-29';

function professional(slots) {
    return {
        id: 4,
        nombre: 'Dra. Ana Quispe',
        especialidad: 'Medicina física',
        dias: [{ fecha: date, slots: slots || [] }],
    };
}

function free(start, end) {
    return { inicio: start, fin: end, estado: 'DISPONIBLE', site_id: 1 };
}

function appointment(id, start, end, state) {
    return {
        backgroundColor: state === 'CONFIRMADO' ? '#e4eff7' : '#fdf0d9',
        borderColor: state === 'CONFIRMADO' ? '#1f5f8b' : '#8a5300',
        textColor: '#27313a',
        extendedProps: {
            tipo_contexto: 'cita_existente',
            appointment_id: id,
            patient_id: id + 100,
            doctor_id: 4,
            doctor: 'Dra. Ana Quispe',
            especialidad: 'Medicina física',
            fecha: date,
            hora_inicio: start,
            hora_fin: end,
            minutos: grid.minutes(end) - grid.minutes(start),
            estado_cita: state || 'PROGRAMADO',
            estado_pagado: id % 2 ? 'PENDIENTE' : 'PAGADO',
            historia_clinica: 'HC' + id,
            paciente: 'PACIENTE DEMO ' + id,
            servicio: 'Consulta',
            leyenda: state || 'PROGRAMADO',
            etiqueta: state === 'CONFIRMADO' ? 'Confirmada' : 'Programada',
            site_id: 1,
        },
    };
}

function build(events, slots) {
    return grid.build({
        date: date,
        gridMinutes: 20,
        professional: professional(slots),
        events: events || [],
    });
}

test('genera una fila exacta cada veinte minutos y separa 10:00 de 10:20', () => {
    const model = build();

    assert.equal(model.rows.length, 39);
    assert.deepEqual(
        model.rows.filter((row) => row.start === '10:00' || row.start === '10:20')
            .map((row) => [row.start, row.end]),
        [['10:00', '10:20'], ['10:20', '10:40']]
    );
});

test('toda fila entrega contexto aunque fuera de horario no sea agendable', () => {
    const model = build([], [free('09:00', '09:20')]);
    const available = model.rows.find((row) => row.start === '09:00');
    const offHours = model.rows.find((row) => row.start === '07:20');

    assert.equal(available.context.tipo_contexto, 'slot_libre');
    assert.equal(available.context.seleccionable, true);
    assert.equal(selection.fromContext(available.context, 'Sede Central').mode, 'Slot libre');
    assert.equal(offHours.context.tipo_contexto, 'fuera_horario');
    assert.equal(offHours.context.seleccionable, false);
    assert.equal(selection.fromContext(offHours.context, 'Sede Central').mode, 'Fuera de horario');
    assert.equal(selection.fromContext(offHours.context, 'Sede Central').status, 'Fuera de horario');
});

test('una cita a las 09:15 conserva su hora real en la fila 09:00', () => {
    const model = build([appointment(1, '09:15', '09:45')]);
    const row = model.rows.find((candidate) => candidate.start === '09:00');

    assert.equal(row.kind, 'appointment');
    assert.equal(row.items[0].context.hora_inicio, '09:15');
});

test('duraciones de 30 y 45 minutos producen continuaciones que seleccionan la misma cita', () => {
    const thirty = build([appointment(1, '09:00', '09:30')]);
    const fortyFive = build([appointment(2, '10:00', '10:45')]);

    assert.equal(thirty.rows.find((row) => row.start === '09:20').items[0].kind, 'continuation');
    assert.equal(thirty.rows.find((row) => row.start === '09:20').items[0].context.appointment_id, 1);
    assert.equal(fortyFive.rows.find((row) => row.start === '10:20').items[0].context.appointment_id, 2);
    assert.equal(fortyFive.rows.find((row) => row.start === '10:40').items[0].context.appointment_id, 2);
});

test('dos citas dentro del mismo bucket se conservan como subfilas ordenadas', () => {
    const model = build([
        appointment(2, '09:15', '09:35', 'CONFIRMADO'),
        appointment(1, '09:05', '09:25', 'PROGRAMADO'),
    ]);
    const row = model.rows.find((candidate) => candidate.start === '09:00');

    assert.equal(row.items.length, 2);
    assert.deepEqual(row.items.map((item) => item.context.hora_inicio), ['09:05', '09:15']);
    assert.deepEqual(row.items.map((item) => item.context.patient_id), [101, 102]);
});

test('el contexto de cita mantiene pago historia clínica y paciente reales', () => {
    const model = build([appointment(3, '09:45', '10:05')]);
    const item = model.rows.find((row) => row.start === '09:40').items[0];
    const quick = selection.fromContext(item.context, 'Sede Central');

    assert.equal(item.context.hora_inicio, '09:45');
    assert.equal(quick.payment, 'PENDIENTE');
    assert.equal(quick.clinicalRecord, 'HC3');
    assert.equal(quick.patient, 'PACIENTE DEMO 3');
});

test('cabecera y filas comparten la única definición de columnas operativas', () => {
    const css = fs.readFileSync(
        path.join(__dirname, '../../public/css/scheduling/agenda.css'),
        'utf8'
    );

    assert.match(css, /\.agenda-row-head[\s\S]*?grid-template-columns:\s*var\(--agenda-day-columns\)/);
    assert.match(css, /\.agenda-day-entry[\s\S]*?grid-template-columns:\s*var\(--agenda-day-columns\)/);
});
