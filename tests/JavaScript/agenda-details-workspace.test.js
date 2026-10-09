const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function workspace() {
    const elements = new Map(), requests = [];
    const element = () => ({ value: '', hidden: true, disabled: false, checked: false, files: [], dataset: {}, handlers: {},
        addEventListener(name, handler) { this.handlers[name] = handler; }, replaceChildren() {},
        classList: { add() {}, remove() {} }, focus() {}, scrollIntoView() {} });
    const document = { getElementById(id) { if (!elements.has(id)) elements.set(id, element()); return elements.get(id); },
        querySelector() { return { content: 'csrf-test' }; }, createElement: element };
    const patient = { id: 2, telefono: '999000002', telefono_secundario: null, channel_id: null, interaction_medium_id: null };
    const selected = { appointment_id: 7, patient_id: 2, estado_agenda: 'PENDIENTE_CONFIRMACION' };
    let fail = false;
    const sandbox = { window: { AgendaGuidance: require('../../public/js/scheduling/agenda-guidance') }, document,
        fetch: async (url, options) => {
            requests.push({ url, options });
            let body;
            if (url.endsWith('/economy')) body = { precio: '150.00', saldo: '150.00', pago_real: '0.00', can_edit_notes: true,
                motivo_consulta: 'Original', observaciones: 'Seguimiento' };
            else if (url.endsWith('/documents')) body = { documents: [] };
            else if (url.endsWith('/notes')) body = { message: 'Motivo y observación guardados.', motivo_consulta: 'Control', observaciones: 'Seguimiento' };
            else body = { message: 'Datos del paciente guardados.', patient };
            if (fail && options.method === 'PATCH') body = { message: 'No tienes permiso para esta acción.' };
            return { ok: !fail, status: fail ? 403 : 200, json: async () => body };
        } };
    for (const name of ['agenda-operational-form', 'agenda-operational-workspace']) {
        vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/scheduling/' + name + '.js'), 'utf8'), sandbox);
        if (name === 'agenda-operational-form') sandbox.window.AgendaOperationalForm = sandbox.AgendaOperationalForm;
    }
    const widget = sandbox.window.AgendaOperationalWorkspace({ base: '/appointments', patientTemplate: '/patients/__PATIENT__',
        canCreate: true, context: () => selected, notice() {}, error: body => body.message });
    return { widget, requests, field: id => document.getElementById('agenda-op-' + id), fail: () => { fail = true; } };
}

test('guardar contacto y notas usa PATCH contextual, conserva vacíos y nunca crea una cita', async () => {
    const qa = workspace();
    await qa.widget.patientChanged(2);
    await qa.widget.selectionChanged({ appointment_id: 7, patient_id: 2, estado_agenda: 'PENDIENTE_CONFIRMACION' });
    qa.field('phone').value = '999000009'; qa.field('phone-secondary').value = '';
    await qa.field('save-patient').handlers.click();
    qa.field('reason').value = 'Control'; qa.field('note').value = '';
    await qa.field('save-notes').handlers.click();
    const writes = qa.requests.filter(r => r.options.method);
    assert.equal(writes.length, 2);
    assert.deepEqual(writes.map(r => r.options.method), ['PATCH', 'PATCH']);
    assert.equal(writes[0].url, '/patients/2/agenda-contact');
    assert.deepEqual(JSON.parse(writes[0].options.body), { telefono: '999000009', appointment_id: 7 });
    assert.equal(writes[1].url, '/appointments/7/notes');
    assert.deepEqual(JSON.parse(writes[1].options.body), { motivo_consulta: 'Control' });
    assert.equal(qa.field('note').value, 'Seguimiento');
    assert.equal(qa.field('patient-status').textContent, 'Datos del paciente guardados.');
    assert.equal(qa.field('notes-status').textContent, 'Motivo y observación guardados.');
});

test('un error de guardado permanece junto a los campos y no se anuncia como éxito', async () => {
    const qa = workspace(); await qa.widget.patientChanged(2); qa.fail();
    await qa.field('save-patient').handlers.click();
    assert.equal(qa.field('patient-error').hidden, false);
    assert.equal(qa.field('patient-error').textContent, 'No tienes permiso para esta acción.');
    assert.equal(qa.field('patient-status').hidden, true);
    assert.equal(qa.field('save-patient').disabled, false);
});
