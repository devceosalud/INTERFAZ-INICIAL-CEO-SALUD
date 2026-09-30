'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const weekEvent = require('../../public/js/scheduling/agenda-week-event.js');
const selection = require('../../public/js/scheduling/agenda-selection.js');

function context(minutes, overrides) {
    return Object.assign({
        appointment_id: 21,
        patient_id: 121,
        hora_inicio: '09:45',
        hora_fin: minutes === 15 ? '10:00' : minutes === 30 ? '10:15' : '10:30',
        minutos: minutes,
        paciente: 'RUIZ DEMO CARLOS',
        servicio: 'Consulta Medicina General',
        numero_identidad: 'NO DEBE EXPONERSE',
        telefono: 'NO DEBE EXPONERSE',
        email: 'NO DEBE EXPONERSE',
    }, overrides || {});
}

test('una cita corta prioriza hora real y paciente en una sola presentación compacta', () => {
    const event = weekEvent.build(context(15));

    assert.equal(event.time, '09:45');
    assert.equal(event.start, '09:45');
    assert.equal(event.end, '');
    assert.equal(event.patient, 'RUIZ DEMO CARLOS');
    assert.equal(event.service, '');
    assert.equal(event.compact, true);
});

test('una cita de 30 minutos conserva hora y paciente sin forzar una línea de servicio', () => {
    const event = weekEvent.build(context(30));

    assert.equal(event.time, '09:45');
    assert.equal(event.start, '09:45');
    assert.equal(event.end, '');
    assert.equal(event.patient, 'RUIZ DEMO CARLOS');
    assert.equal(event.service, '');
});

test('una cita de 45 minutos puede mostrar el servicio porque dispone de mayor altura', () => {
    const event = weekEvent.build(context(45));

    assert.equal(event.time, '09:45–10:30');
    assert.equal(event.start, '09:45');
    assert.equal(event.end, '10:30');
    assert.equal(event.patient, 'RUIZ DEMO CARLOS');
    assert.equal(event.service, 'Consulta Medicina General');
    assert.equal(event.compact, false);
});

test('un nombre largo se conserva completo en el modelo y el recorte queda solo en CSS', () => {
    const name = 'FERNÁNDEZ DE LA TORRE MARÍA ALEJANDRA DEL CARMEN';

    assert.equal(weekEvent.build(context(20, { paciente: name })).patient, name);
});

test('el renderer semanal no propaga DNI teléfono ni correo aunque existan en la entrada', () => {
    const event = weekEvent.build(context(45));

    assert.deepEqual(
        Object.keys(event).sort(),
        ['compact', 'duration', 'end', 'patient', 'service', 'start', 'time']
    );
    assert.equal(JSON.stringify(event).includes('NO DEBE EXPONERSE'), false);
});

test('seleccionar el evento semanal conserva la misma cita y el mismo paciente', () => {
    const source = context(30);
    const selected = selection.fromContext(Object.assign({
        tipo_contexto: 'cita_existente',
        doctor: 'Dra. Operativa',
        especialidad: 'Medicina General',
        fecha: '2026-09-29',
        estado_cita: 'CONFIRMADO',
        estado_pagado: 'PENDIENTE',
        historia_clinica: 'HC-121',
    }, source), 'Sede Central');

    assert.equal(source.appointment_id, 21);
    assert.equal(selected.patientId, '121');
    assert.equal(selected.patient, 'RUIZ DEMO CARLOS');
    assert.equal(selected.time, '09:45 – 10:15');
    assert.equal(selected.mode, 'Cita existente');
});

test('el CSS conserva la geometría temporal y hace progresivo el rango final', () => {
    const css = fs.readFileSync(
        path.join(__dirname, '../../public/css/scheduling/agenda.css'),
        'utf8'
    );
    const agendaScript = fs.readFileSync(
        path.join(__dirname, '../../public/js/scheduling/agenda.js'),
        'utf8'
    );

    assert.match(
        css,
        /\.agenda-calendar\.fc \.agenda-calendar-event--appointment \{[\s\S]*?margin-block:\s*0;[\s\S]*?padding:\s*0/
    );
    assert.match(css, /container-type:\s*inline-size/);
    assert.match(css, /\.agenda-week-event__time-end\s*\{\s*display:\s*none/);
    assert.match(
        css,
        /@container \(min-width:\s*150px\)[\s\S]*?\.agenda-week-event__time-end\s*\{\s*display:\s*inline/
    );
    assert.match(
        css,
        /\.agenda-board--week \.agenda-calendar\.fc \.agenda-calendar-event--appointment \{[\s\S]*?margin:\s*0 1px;[\s\S]*?border-style:\s*solid;[\s\S]*?border-radius:\s*1px/
    );
    assert.match(
        css,
        /\.agenda-board--week \.agenda-calendar\.fc \.agenda-calendar-event--appointment \.fc-event-main,[\s\S]*?\.agenda-board--week \.agenda-calendar\.fc \.agenda-week-event__service \{[\s\S]*?background:\s*transparent !important;[\s\S]*?border:\s*0;/
    );
    assert.match(
        agendaScript,
        /board\.classList\.toggle\('agenda-board--week', view === 'semana'\)/
    );
});
