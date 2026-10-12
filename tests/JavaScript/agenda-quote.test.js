const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const settle = () => new Promise(resolve => setImmediate(resolve));

function workspace() {
    const elements = new Map(), pending = [], calls = [];
    const element = () => ({ value: '', hidden: true, disabled: false, checked: false, files: [], dataset: {}, handlers: {},
        addEventListener(name, handler) { this.handlers[name] = handler; }, replaceChildren() {},
        classList: { add() {}, remove() {} }, focus() {}, scrollIntoView() {} });
    const document = { getElementById(id) { if (!elements.has(id)) elements.set(id, element()); return elements.get(id); },
        querySelector() { return { content: 'test-csrf' }; }, createElement: element };
    let patientId = 2, serviceId = 3;
    let selected = { tipo_contexto: 'slot_libre', doctor_id: 1, fecha: '2026-10-13', hora_inicio: '09:00' };
    const sandbox = { document, window: { AgendaGuidance: require('../../public/js/scheduling/agenda-guidance') },
        fetch: (url, options = {}) => {
            calls.push({ url, options });
            if (url === '/quote') return new Promise(resolve => pending.push(resolve));
            const body = url.endsWith('/economy') ? { precio: '175.00', saldo: '175.00', pago_real: '0.00' }
                : url.endsWith('/documents') ? { documents: [] } : { patient: { telefono: '' } };
            return Promise.resolve({ ok: true, status: 200, json: async () => body });
        } };
    for (const name of ['agenda-operational-form', 'agenda-operational-workspace']) {
        vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/scheduling/' + name + '.js'), 'utf8'), sandbox);
        if (name === 'agenda-operational-form') sandbox.window.AgendaOperationalForm = sandbox.AgendaOperationalForm;
    }
    const widget = sandbox.window.AgendaOperationalWorkspace({ base: '/appointments', quoteEndpoint: '/quote',
        patientTemplate: '/patients/__PATIENT__', context: () => selected, canCreate: true, notice() {}, error: b => b.message,
        quoteData: () => ({ doctor_id: selected.doctor_id, service_id: serviceId, patient_id: patientId, fecha_cita: selected.fecha }) });
    return { widget, pending, calls, field: id => document.getElementById('agenda-op-' + id),
        select(value) { selected = value; }, patient(value) { patientId = value; }, service(value) { serviceId = value; }, answer(index, price, ok = true) {
            pending[index]({ ok, status: ok ? 200 : 422, json: async () => ok ? { precio_programado: price } : { message: 'Tarifa no vigente' } });
        } };
}

test('la cotización en servidor incluye tarifa y bloquea precio hasta recibir el resultado', async () => {
    const qa = workspace(); qa.field('rate').value = '7'; qa.widget.quoteChanged(100);
    assert.equal(qa.widget.readiness().price, '0.00');
    assert.deepEqual(JSON.parse(qa.calls[0].options.body), { doctor_id: 1, service_id: 3, patient_id: 2, fecha_cita: '2026-10-13', additional_rate_id: '7' });
    qa.answer(0, '80.00'); await settle();
    assert.equal(qa.widget.readiness().price, '80.00');
    assert.equal(qa.field('balance').value, '80.00');
    assert.equal(qa.field('quote-status').hidden, true);
});

test('respuestas fuera de orden no reemplazan el precio de la selección actual', async () => {
    const qa = workspace(); qa.widget.quoteChanged(100); qa.widget.quoteChanged(100);
    qa.answer(1, '90.00'); await settle(); qa.answer(0, '50.00'); await settle();
    assert.equal(qa.widget.readiness().price, '90.00');
});

test('una cita persistida conserva su precio aunque llegue una cotización anterior', async () => {
    const qa = workspace(); qa.widget.quoteChanged(100);
    const selected = { tipo_contexto: 'cita_existente', appointment_id: 5, patient_id: 2, estado_agenda: 'CONFIRMADA' };
    qa.select(selected); await qa.widget.selectionChanged(selected);
    qa.answer(0, '50.00'); await settle();
    assert.equal(qa.field('price').value, '175.00'); assert.equal(qa.field('rate').disabled, true);
});

test('un fallo de precio conserva el registro bloqueado y explica la tarifa inválida', async () => {
    const qa = workspace(); qa.widget.quoteChanged(100); qa.answer(0, '', false); await settle();
    assert.equal(qa.widget.readiness().price, '0.00');
    assert.equal(qa.field('quote-status').hidden, false); assert.equal(qa.field('quote-status').textContent, 'Tarifa no vigente');
    assert.deepEqual(qa.calls.map(c => c.url), ['/quote']);
});

// Execute the real identity painter together with the real async quote workspace.
function identityPainter(qa) {
    const source = fs.readFileSync(path.join(__dirname, '../../public/js/scheduling/agenda.js'), 'utf8');
    const painter = source.slice(source.indexOf('    function paintIdentity(identity) {'), source.indexOf('    function showStoredAppointment(context) {'));
    const field = () => ({ hidden: false, disabled: false, textContent: '', className: '' });
    const el = Object.fromEntries(['quickPatientIdDisplay', 'quickPatientState', 'lookupClinicalRecord', 'lookupResult',
        'patientRegister', 'completeRegistration', 'completeRegistrationHelp'].map(key => [key, field()]));
    let currentPatient = 2;
    el.quickPatientId = { get value() { return currentPatient; }, set value(value) { currentPatient = value; qa.patient(value); } };
    const sandbox = { state: {}, el, operationalWorkspace: { patientChanged(id) {
        assert.equal(el.quickPatientId.value, id, 'La identidad del formulario debe actualizarse antes de cotizar');
        return qa.widget.patientChanged(id);
    } },
        window: { AgendaGuidance: { copy: { completeChart: '' } } },
        paintDraft() {}, draftModel: { discard() {} }, scheduleSnapshot() {}, updateAppointmentAction() {} };
    vm.runInNewContext(painter + '\nglobalThis.paint = paintIdentity;', sandbox);
    return id => sandbox.paint({ patientId: id, status: 'found' });
}

test('cambiar entre primera consulta y reconsulta cotiza la identidad nueva antes de respuestas tardías', async () => {
    const qa = workspace(), paint = identityPainter(qa);
    paint(2); // fictional first consultation S/100
    paint(3); // fictional attended patient, reconsultation S/50
    const quotes = qa.calls.filter(call => call.url === '/quote');
    assert.deepEqual(quotes.map(call => JSON.parse(call.options.body).patient_id), [2, 3]);
    qa.answer(1, '50.00'); await settle();
    qa.answer(0, '100.00'); await settle();
    assert.equal(qa.widget.readiness().price, '50.00');
    paint(2); qa.answer(2, '100.00'); await settle();
    assert.equal(qa.widget.readiness().price, '100.00');
});

for (const dimension of ['doctor', 'service', 'date', 'rate']) {
    test('cambiar ' + dimension + ' invalida la respuesta anterior y usa los parámetros vigentes', async () => {
        const qa = workspace(); qa.widget.quoteChanged(100);
        if (dimension === 'doctor') qa.select({ doctor_id: 8, fecha: '2026-10-13', hora_inicio: '09:00' });
        if (dimension === 'service') qa.service(9);
        if (dimension === 'date') qa.select({ doctor_id: 1, fecha: '2026-10-15', hora_inicio: '09:00' });
        if (dimension === 'rate') { qa.field('rate').value = '7'; qa.field('rate').handlers.change(); }
        else qa.widget.quoteChanged(100);
        const latest = JSON.parse(qa.calls.filter(call => call.url === '/quote')[1].options.body);
        assert.equal(latest[{ doctor: 'doctor_id', service: 'service_id', date: 'fecha_cita', rate: 'additional_rate_id' }[dimension]],
            { doctor: 8, service: 9, date: '2026-10-15', rate: '7' }[dimension]);
        qa.answer(1, '80.00'); await settle(); qa.answer(0, '', false); await settle();
        assert.equal(qa.widget.readiness().price, '80.00');
        assert.equal(qa.field('quote-status').hidden, true);
    });
}
