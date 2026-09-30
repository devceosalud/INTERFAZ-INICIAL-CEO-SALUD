'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const backgrounds = require('../../public/js/scheduling/agenda-week-background.js');

function professional(days) {
    return [{
        id: 4,
        dias: days,
    }];
}

function day(fecha, slots) {
    return { fecha, slots };
}

function slot(inicio, fin, estado) {
    return { inicio, fin, estado: estado || 'DISPONIBLE' };
}

test('la disponibilidad continua queda como grilla natural y solo pinta sus extremos externos', () => {
    const events = backgrounds.build(professional([
        day('2026-09-29', [
            slot('08:00', '08:20'),
            slot('08:20', '08:40'),
            slot('08:40', '09:00', 'OCUPADO'),
        ]),
    ]), '07:00', '20:00');

    assert.deepEqual(events.map((event) => [event.start, event.end]), [
        ['2026-09-29T07:00:00', '2026-09-29T08:00:00'],
        ['2026-09-29T09:00:00', '2026-09-29T20:00:00'],
    ]);
    assert.ok(events.every((event) => event.display === 'background'));
    assert.ok(events.every((event) => event.backgroundColor === backgrounds.OUTSIDE_BACKGROUND));
    assert.ok(events.every((event) => event.classNames.includes('agenda-outside-hours-background')));
});

test('un descanso entre bloques genera un único fondo gris continuo', () => {
    const events = backgrounds.build(professional([
        day('2026-09-29', [
            slot('08:00', '10:00'),
            slot('11:00', '13:00'),
        ]),
    ]), '07:00', '20:00');

    assert.deepEqual(events.map((event) => [event.start, event.end]), [
        ['2026-09-29T07:00:00', '2026-09-29T08:00:00'],
        ['2026-09-29T10:00:00', '2026-09-29T11:00:00'],
        ['2026-09-29T13:00:00', '2026-09-29T20:00:00'],
    ]);
});

test('un día sin horario se representa con un solo fondo gris', () => {
    const events = backgrounds.build(
        professional([day('2026-09-30', [])]),
        '07:00',
        '20:00'
    );

    assert.equal(events.length, 1);
    assert.equal(events[0].start, '2026-09-30T07:00:00');
    assert.equal(events[0].end, '2026-09-30T20:00:00');
});

test('los intervalos ocupados siguen formando parte del horario configurado', () => {
    const events = backgrounds.build(professional([
        day('2026-09-29', [
            slot('08:00', '08:20'),
            slot('08:20', '08:40', 'OCUPADO'),
            slot('08:40', '09:00'),
        ]),
    ]), '08:00', '09:00');

    assert.deepEqual(events, []);
});

test('Agenda no vuelve a convertir cada slot libre en un background blanco', () => {
    const agendaScript = fs.readFileSync(
        path.join(__dirname, '../../public/js/scheduling/agenda.js'),
        'utf8'
    );
    const css = fs.readFileSync(
        path.join(__dirname, '../../public/css/scheduling/agenda.css'),
        'utf8'
    );

    assert.doesNotMatch(agendaScript, /agenda-availability-background/);
    assert.doesNotMatch(agendaScript, /backgroundColor:\s*['"]#ffffff['"]/);
    assert.match(agendaScript, /weekBackgroundModel\.build/);
    assert.match(agendaScript, /setOption\('firstDay', state\.view === 'semana' \? 1 : 0\)/);
    assert.match(css, /\.agenda-board--week \.agenda-calendar\.fc \.fc-bg-event\.agenda-outside-hours-background/);
    assert.match(css, /\.fc-timegrid-slot \{[\s\S]*?background-color:\s*#fff !important/);
});
