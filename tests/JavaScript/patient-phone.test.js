'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const phone = require('../../public/js/patients/patient-phone.js');
const draft = require('../../public/js/scheduling/agenda-patient-draft.js');

const schedule = {
    doctor: 'Dra. Ana',
    specialty: 'Medicina',
    date: '2026-10-05',
    time: '10:10 – 10:20',
    site: 'Sede Central',
};

test('el correo vacío es válido y un texto sin dominio no', () => {
    assert.equal(phone.emailMessage(''), '');
    assert.equal(phone.emailMessage('persona@dominio.com'), '');
    assert.equal(phone.emailMessage('abc'), 'Ingresa un correo electrónico válido.');
    assert.equal(phone.emailMessage('correo@'), 'Ingresa un correo electrónico válido.');
});

test('Perú +51 es el prefijo por defecto y un internacional se separa', () => {
    assert.deepEqual(phone.split(''), { parsed: true, prefijo: '+51', numero: '', raw: '' });
    assert.deepEqual(phone.split('+51987654321'), { parsed: true, prefijo: '+51', numero: '987654321', raw: '' });
    assert.deepEqual(phone.split('+541112345678'), { parsed: true, prefijo: '+54', numero: '1112345678', raw: '' });
    assert.equal(phone.split('anexo 12').parsed, false);
    assert.equal(phone.split('anexo 12').raw, 'anexo 12');
    assert.equal(phone.compose('+51', '987654321'), '+51987654321');
    assert.equal(phone.compose('+54', '1112345678'), '+541112345678');
});

test('el alta desde Agenda conserva el contexto y envía el teléfono separado', () => {
    const opened = draft.open({
        status: 'not_found',
        showRegister: true,
        tipo: 'DNI',
        numero: '73378485',
    }, schedule);
    const edited = draft.edit(draft.edit(opened, 'telefono_prefijo', '+51'), 'telefono_numero', '987654321');
    const payload = draft.toPayload(edited);

    assert.equal(edited.doctor, schedule.doctor);
    assert.equal(edited.time, schedule.time);
    assert.equal(edited.site, schedule.site);
    assert.equal(payload.telefono_prefijo, '+51');
    assert.equal(payload.telefono_numero, '987654321');
    assert.equal(payload.registrar_responsable, false);
    assert.equal(draft.saveOutcome(true, false).attach, true);
    assert.match(draft.saveOutcome(true, false).message, /Registro rápido/);
    assert.equal(draft.saveOutcome(false, false).attach, false);
});

test('una ficha con teléfono internacional separa prefijo y número', () => {
    const opened = draft.openExisting({
        id: 17,
        tipo_identificacion: 'DNI',
        numero_identidad: '73378485',
        telefono: '+51987654321',
        nombre: 'Maria',
    }, schedule);

    assert.equal(opened.patientId, '17');
    assert.equal(opened.telefono_prefijo, '+51');
    assert.equal(opened.telefono_numero, '987654321');
    assert.equal(opened.telefono_sin_separar, false);
    assert.equal(opened.doctor, schedule.doctor);
    assert.equal(opened.time, schedule.time);
});
