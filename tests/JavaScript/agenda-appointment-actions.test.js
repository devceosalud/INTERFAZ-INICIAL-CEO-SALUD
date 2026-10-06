const test = require('node:test');
const assert = require('node:assert/strict');
const actions = require('../../public/js/scheduling/agenda-appointment-actions');
const context = { appointment_id: 7, fecha: '2026-10-09', hora_inicio: '10:00' };

test('adicional usa el slot médico real y exige seleccionar nuevamente paciente/servicio', () => {
    const selected = Object.assign({}, context, { doctor_id: 1, patient_id: 42, paciente: 'PREVIOUS',
        service_id: 3, precio_programado: 150, total_pagado: 80, historia_clinica: 'SECRET' });
    const extra = actions.additionalContext(selected, { inicio: '10:00', fin: '10:20', minutos: 20, site_id: null, estado: 'OCUPADO' });
    assert.equal(extra.minutos, 20); assert.equal(extra.patient_id, null); assert.equal(extra.appointment_id, null);
    assert.equal(extra.doctor_id, 1); assert.equal(extra.leyenda, 'ADICIONAL');
    assert.equal(extra.seleccionable, true);
    assert.doesNotMatch(JSON.stringify(extra), /PREVIOUS|SECRET|precio_programado|total_pagado|service_id/);
    assert.throws(() => actions.additionalContext(selected, null), /intervalo real/);
});

test('cancelación revierte el drag y no envía ninguna petición', async () => {
    let reverted = 0, sent = 0;
    const result = await actions.reschedule(context, '2026-10-10', '11:00', {
        confirm: () => false, revert: () => reverted++, send: () => sent++, refresh: () => {},
    });
    assert.equal(result.cancelled, true); assert.equal(reverted, 1); assert.equal(sent, 0);
});
test('confirma origen/destino y solo envía fecha/hora; refresca tras persistir', async () => {
    let message, payload, id, refreshed = 0;
    await actions.reschedule(context, '2026-10-10', '11:00', {
        confirm: (text) => { message = text; return true; }, revert: () => assert.fail('no revert'),
        send: async (key, data) => { id = key; payload = data; }, refresh: async () => refreshed++,
    });
    assert.match(message, /2026-10-09 10:00.*2026-10-10 11:00/);
    assert.equal(id, 7); assert.deepEqual(payload, {
        fecha_cita: '2026-10-10', hora_cita: '11:00', expected_fecha_cita: '2026-10-09', expected_hora_cita: '10:00',
    });
    assert.equal(refreshed, 1);
});
test('409/422 o red fallida revierten el evento sin refresco exitoso', async () => {
    for (const status of [409, 422, 500]) {
        let reverted = 0;
        await assert.rejects(actions.reschedule(context, '2026-10-10', '11:00', {
            confirm: () => true, revert: () => reverted++, send: async () => { throw new Error(String(status)); },
            refresh: () => assert.fail('no success refresh'),
        }), new RegExp(String(status)));
        assert.equal(reverted, 1);
    }
});
