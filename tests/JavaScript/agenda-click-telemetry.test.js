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

test('v3 conserva solo geometría técnica sin leer contenido, IDs ni valores de inputs', () => {
    const rect={left:550,top:130,width:800,height:600};
    const geometry={...rect,operations_scroll:40,doctors_scroll:0,grid_scroll:0,page_scroll:0,expanded:['capture'],selected:false,revision:1};
    const item=telemetry.event({clientX:950,clientY:280,target:target('SECRET')},1366,768,'agenda','dia','grid',rect,'uuid',geometry);
    assert.equal(item.layout_version,3); assert.equal(item.x,0.5); assert.equal(item.y,0.25);
    assert.deepEqual(item.geometry,geometry);
    assert.equal(telemetry.event({clientX:0,clientY:0,target:target('SECRET')},1366,768,'agenda','dia','grid',rect,'uuid',geometry),null);
    assert.doesNotMatch(JSON.stringify(item),/SECRET|patient_id|appointment_id|user_id|input_value|textContent/);
});
