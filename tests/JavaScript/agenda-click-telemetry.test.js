const test = require('node:test');
const assert = require('node:assert/strict');
const telemetry = require('../../public/js/scheduling/agenda-click-telemetry');
function target(id, parentElement = null, dataset = {}) {
    return { id, parentElement, dataset, classList: { contains: () => false },
        get value() { throw new Error('must never read input value'); },
        get textContent() { throw new Error('must never read clicked text'); } };
}
test('solo serializa UI normalizada y un identificador semántico controlado', () => {
    const event = telemetry.event({ clientX: 320, clientY: 200, target: target('free-patient-text', target('agenda-patient-lookup')) }, 1280, 800, 'dia', 'anonymous-uuid');
    assert.deepEqual(event, { event_uuid: 'anonymous-uuid', screen: 'agenda', view_mode: 'dia',
        element: 'agenda.patient_search', x: 0.25, y: 0.25, viewport_width: 1280, viewport_height: 800 });
});
test('un ID arbitrario y un ID de cita nunca se envían como datos', () => {
    const event = telemetry.event({ clientX: -5, clientY: 1000, target: target('PATIENT-SECRET', null, { appointmentId: 987 }) }, 1280, 800, 'semana', 'uuid');
    assert.equal(event.element, 'agenda.appointment'); assert.equal(event.x, 0); assert.equal(event.y, 1);
    assert.doesNotMatch(JSON.stringify(event), /SECRET|987/);
});
test('batch acotado y fallos del endpoint no bloquean las acciones de Agenda', async () => {
    let size, sends = 0;
    const queue = telemetry.queue(async (batch) => { size = batch.events.length; sends++; throw new Error('503'); });
    for (let i = 0; i < 30; i++) { queue.add({ event_uuid: String(i) }); }
    await assert.doesNotReject(queue.flush()); assert.equal(size, 20);
    await assert.doesNotReject(queue.flush()); assert.equal(size, 10); assert.equal(sends, 2);
});
