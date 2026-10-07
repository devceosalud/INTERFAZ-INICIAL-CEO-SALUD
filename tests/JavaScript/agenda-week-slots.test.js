'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const weekSlots = require('../../public/js/scheduling/agenda-week-slots.js');
const selection = require('../../public/js/scheduling/agenda-selection.js');

function slotEvent(start, end, minutes, extra) {
    return Object.assign({
        id: 'slot-' + start.slice(11, 16).replace(':', ''),
        start: start,
        end: end,
        extendedProps: Object.assign({
            tipo_contexto: 'slot_libre',
            seleccionable: true,
            hora_inicio: start.slice(11, 16),
            hora_fin: end.slice(11, 16),
            minutos: minutes,
            doctor_id: 4,
            doctor: 'Dra. Ana',
            especialidad: 'Medicina',
            site_id: 2,
            fecha: '2026-10-05',
        }, extra || {}),
    }, extra && extra.event || {});
}

function cadence(step, from, until) {
    const events = [];
    let cursor = from;

    while (cursor + step <= until) {
        const start = minutesToIso(cursor);
        const end = minutesToIso(cursor + step);
        events.push(slotEvent(start, end, step));
        cursor += step;
    }

    return events;
}

function minutesToIso(minutes) {
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;

    return '2026-10-05T' + String(hours).padStart(2, '0') + ':' + String(remainder).padStart(2, '0') + ':00';
}

function starts(events) {
    return weekSlots.hits(events).map((hit) => hit.extendedProps.hora_inicio);
}

test('cadencia 10: cada slot real del feed es un hit, incluida la media celda', () => {
    const hits = weekSlots.hits(cadence(10, 10 * 60, 12 * 60));

    assert.deepEqual(
        ['10:00', '10:10', '10:20', '10:30', '10:50'].map((time) => hits.some((hit) => hit.extendedProps.hora_inicio === time)),
        [true, true, true, true, true]
    );
    assert.ok(hits.every((hit) => hit.extendedProps.minutos === 10));
    assert.equal(hits.length, 12);
    hits.forEach((hit) => {
        const quick = selection.fromContext(hit.extendedProps);
        assert.equal(quick.time, hit.extendedProps.hora_inicio + ' – ' + hit.extendedProps.hora_fin);
        assert.equal(quick.duration, '10 min');
        assert.equal(quick.doctor, 'Dra. Ana');
        assert.equal(quick.specialty, 'Medicina');
        assert.equal(quick.date, '2026-10-05');
    });
});

test('cadencia 20 conserva solo los inicios reales de 20 minutos', () => {
    const found = starts(cadence(20, 10 * 60, 12 * 60));

    assert.ok(found.includes('10:00'));
    assert.ok(found.includes('10:20'));
    assert.equal(found.includes('10:10'), false);
    assert.ok(weekSlots.hits(cadence(20, 10 * 60, 11 * 60)).every((hit) => hit.extendedProps.minutos === 20));
});

test('cadencia 30 no inventa un slot que empiece a las 10:20', () => {
    const hits = weekSlots.hits(cadence(30, 10 * 60, 12 * 60));

    assert.deepEqual(hits.map((hit) => [hit.extendedProps.hora_inicio, hit.extendedProps.hora_fin]), [
        ['10:00', '10:30'],
        ['10:30', '11:00'],
        ['11:00', '11:30'],
        ['11:30', '12:00'],
    ]);
    assert.equal(hits.some((hit) => hit.extendedProps.hora_inicio === '10:20'), false);
    assert.ok(hits.every((hit) => hit.extendedProps.minutos === 30));
});

test('una cita y un slot ocupado no se convierten en hit de disponibilidad', () => {
    const hits = weekSlots.hits([
        slotEvent('2026-10-05T10:00:00', '2026-10-05T10:10:00', 10),
        {
            id: 'cita-1',
            start: '2026-10-05T10:10:00',
            end: '2026-10-05T10:20:00',
            extendedProps: { tipo_contexto: 'cita_existente', hora_inicio: '10:10', hora_fin: '10:20', minutos: 10 },
        },
    ]);

    assert.equal(hits.length, 1);
    assert.equal(hits[0].extendedProps.tipo_contexto, 'slot_libre');
    assert.equal(hits[0].extendedProps.hora_inicio, '10:00');
});

test('fuera de horario no aparece hit si el feed no trae el slot', () => {
    const hits = weekSlots.hits(cadence(10, 10 * 60, 11 * 60));

    assert.equal(hits.some((hit) => hit.extendedProps.hora_inicio === '09:40'), false);
    assert.equal(hits.some((hit) => hit.extendedProps.hora_inicio === '11:00'), false);
});

test('dos médicos conservan su doctor_id en hits distintos', () => {
    const hits = weekSlots.hits([
        slotEvent('2026-10-05T10:10:00', '2026-10-05T10:20:00', 10, { doctor_id: 4, doctor: 'Dra. Ana' }),
        slotEvent('2026-10-05T10:10:00', '2026-10-05T10:20:00', 10, { doctor_id: 9, doctor: 'Dr. Luis' }),
    ]);

    assert.deepEqual(hits.map((hit) => hit.extendedProps.doctor_id), [4, 9]);
});

test('el hit es transparente y no es un background de disponibilidad', () => {
    const [hit] = weekSlots.hits(cadence(10, 10 * 60, 10 * 60 + 10));

    assert.equal(hit.display, undefined);
    assert.equal(hit.backgroundColor, 'transparent');
    assert.deepEqual(hit.classNames, ['agenda-slot-hit']);
    assert.equal(Object.prototype.hasOwnProperty.call(hit, 'display'), false);
});

test('Semana monta los hits sin volver a pintar backgrounds blancos por slot', () => {
    const agendaScript = fs.readFileSync(path.join(__dirname, '../../public/js/scheduling/agenda.js'), 'utf8');
    const css = fs.readFileSync(path.join(__dirname, '../../public/css/scheduling/agenda.css'), 'utf8');

    assert.match(agendaScript, /weekSlotModel\.hits\(events\)/);
    assert.match(agendaScript, /tipo_contexto === 'slot_libre'/);
    assert.doesNotMatch(agendaScript, /agenda-availability-background/);
    assert.doesNotMatch(agendaScript, /backgroundColor:\s*['"]#ffffff['"]/);
    assert.match(css, /\.fc-event\.agenda-slot-hit/);
    assert.match(css, /background:\s*transparent !important/);
});
