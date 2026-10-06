const test = require('node:test');
const assert = require('node:assert/strict');
const telemetry = require('../../public/js/scheduling/agenda-click-telemetry');
function target(id, parentElement = null, dataset = {}) {
    return { id, parentElement, dataset, classList: { contains: () => false },
        get value() { throw new Error('must never read input value'); },
        get textContent() { throw new Error('must never read clicked text'); } };
}
test('v2 normaliza coordenadas dentro de una zona; jamás lee contenido', () => {
    const item = telemetry.event({ clientX: 320, clientY: 200, target: target('SECRET') }, 1280, 800,
        'agenda', 'dia', 'grid', { left: 120, top: 100, width: 800, height: 400 }, 'anonymous-uuid');
    assert.deepEqual(item, { event_uuid: 'anonymous-uuid', screen: 'agenda', layout_version: 2, zone: 'grid', view_mode: 'dia',
        element: 'agenda.other', x: 0.25, y: 0.25, viewport_width: 1280, viewport_height: 800 });
});
test('los tres módulos usan la misma API y rechazan geometría ajena y campos editables', () => {
    for (const [screen, module] of Object.entries(telemetry.modules)) {
        const rect = { left: 0, top: 0, width: 1000, height: 500 };
        const click = { clientX: 10, clientY: 20, target: target('PATIENT-SECRET', null, { appointmentId: 987 }) };
        const item = telemetry.event(click, 1280, 800, screen, module.views[0], Object.keys(module.zones)[0], rect, 'uuid');
        assert.equal(item.layout_version, 2); assert.equal(item.screen, screen);
        assert.doesNotMatch(JSON.stringify(item), /SECRET|987|patient_id|appointment_id|user_id/);
        assert.equal(telemetry.event(click, 1280, 800, screen, module.views[0], 'unknown', rect, 'uuid'), null);
        click.target.tagName = 'INPUT';
        assert.equal(telemetry.event(click, 1280, 800, screen, module.views[0], 'toolbar', rect, 'uuid'), null);
    }
});
test('batch acotado y fallos del endpoint no bloquean las acciones de Agenda', async () => {
    let size, sends = 0;
    const queue = telemetry.queue(async (batch) => { size = batch.events.length; sends++; throw new Error('503'); });
    for (let i = 0; i < 30; i++) { queue.add({ event_uuid: String(i) }); }
    await assert.doesNotReject(queue.flush()); assert.equal(size, 20);
    await assert.doesNotReject(queue.flush()); assert.equal(size, 10); assert.equal(sends, 2);
});
