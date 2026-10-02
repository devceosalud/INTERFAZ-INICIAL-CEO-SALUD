'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const time = require('../../public/js/scheduling/schedule-time.js');

test('hora 10:00 y 14:30 se conservan como HH:mm', () => {
    assert.equal(time.compose('10', '00'), '10:00');
    assert.equal(time.compose('14', '30'), '14:30');
    assert.equal(time.presentation('10:00').custom, false);
    assert.equal(time.presentation('14:30').value, '14:30');
    assert.equal(time.presentation('14:30').custom, false);
});

test('un minuto heredado 42 se conserva y no se redondea', () => {
    const view = time.presentation('11:42');

    assert.equal(view.value, '11:42');
    assert.equal(view.hour, '11');
    assert.equal(view.minute, '42');
    assert.equal(view.custom, true);
    assert.equal(time.compose('11', '42'), '11:42');
    assert.notEqual(time.compose('11', '42'), '11:40');
    assert.notEqual(time.compose('11', '42'), '11:45');
});

test('el selector ofrece las 24 horas y los minutos rápidos de cinco en cinco', () => {
    assert.equal(time.hours.length, 24);
    assert.equal(time.hours[0], '00');
    assert.equal(time.hours[1], '01');
    assert.equal(time.hours[time.hours.length - 1], '23');
    assert.equal(time.hours.includes('07'), true);
    assert.equal(time.hours.includes('21'), true);
    assert.equal(time.minutes.includes('00'), true);
    assert.equal(time.minutes.includes('30'), true);
    assert.equal(time.minutes.includes('55'), true);
    assert.equal(time.minutes.includes('42'), false);
});

test('00:00, 01:00, 12:00, 21:30 y 23:55 quedan en HH:mm', () => {
    ['00:00', '01:00', '01:30', '12:00', '21:30', '22:15', '23:55', '00:10'].forEach(function (value) {
        const view = time.presentation(value);

        assert.equal(view.value, value);
        assert.equal(view.custom, time.minutes.indexOf(value.slice(3)) === -1);
        assert.equal(time.compose(view.hour, view.minute), value);
    });
});

test('05:42 y un heredado 23:42 se conservan en Otro sin redondear', () => {
    const early = time.presentation('05:42');
    const late = time.presentation('23:42');

    assert.equal(early.hour, '05');
    assert.equal(early.minute, '42');
    assert.equal(early.custom, true);
    assert.equal(early.value, '05:42');
    assert.equal(late.hour, '23');
    assert.equal(late.minute, '42');
    assert.equal(late.custom, true);
    assert.equal(late.value, '23:42');
    assert.equal(time.compose('23', '42'), '23:42');
    assert.notEqual(time.compose('23', '42'), '23:40');
    assert.notEqual(time.compose('23', '42'), '23:45');
});
