const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const guidance = require('../../public/js/scheduling/agenda-guidance');

const root = path.resolve(__dirname, '../..');
function source(file) { return fs.readFileSync(path.join(root, file), 'utf8'); }

test('el par reserva/agendar explica el siguiente paso sin decir que la reserva ocupa el horario', () => {
    const help = guidance.copy.reserveVsSchedule;
    assert.match(help, /Guardar reserva: guarda el seguimiento sin confirmar el horario/);
    assert.match(help, /Agendar cita: confirma el horario con al menos 50% de adelanto/);
    assert.doesNotMatch(help, /bloquea|aparta el horario|ocupa el slot/i);
    for (const file of [
        'resources/views/scheduling/agenda/partials/quick-registration.blade.php',
        'resources/views/scheduling/agenda/partials/patient-modal.blade.php',
    ]) {
        assert.match(source(file), /id="agenda-(booking-help|draft-booking-help)"[^>]*>[^<]*Guardar reserva: guarda el seguimiento/);
    }
});

test('un adelanto menor a 50% se explica en el pago y no abre un modal', () => {
    const placed = guidance.place('Para agendar se requiere adelanto real de al menos 50%. Puedes guardar una reserva privada.', 422, 'payment');
    assert.equal(placed.domain, 'payment');
    assert.equal(placed.focus, true);
    assert.equal(placed.text, guidance.copy.insufficientAdvance);
    assert.doesNotMatch(placed.text, /modal|dialog/i);
});

test('guardar reserva nombra el siguiente paso y los botones apagados explican por qué', () => {
    assert.equal(guidance.copy.reserveSaved, 'Reserva guardada · Pendiente de adelanto.');
    assert.equal(guidance.copy.needSelection, 'Selecciona paciente, servicio y horario.');
    assert.equal(guidance.copy.needAppointment, 'Primero guarda la reserva o selecciona una cita.');
    assert.equal(guidance.copy.needDoctorHour, 'Selecciona una hora del horario del médico.');
    assert.equal(guidance.copy.cashNoOperation, 'No se requiere número de operación para efectivo.');
    assert.match(source('resources/views/scheduling/agenda/partials/quick-registration.blade.php'), /agenda-pending-tip/);
    assert.match(source('resources/views/scheduling/agenda/partials/quick-registration.blade.php'), /agenda-payment-tip/);
});

test('403 no muestra inglés ni el nombre de una capability', () => {
    for (const raw of ['This action is unauthorized.', 'User does not have the right permissions.', 'spatie']) {
        const generic = guidance.place(raw, 403, 'local');
        assert.equal(generic.text, 'No tienes permiso para esta acción.');
        assert.doesNotMatch(generic.text, /unauthorized|spatie|capability/i);
    }
    assert.equal(guidance.place('This action is unauthorized.', 403, 'payment').text, guidance.copy.permissionPayment);
    assert.equal(guidance.place('This action is unauthorized.', 403, 'withdraw').text, guidance.copy.permissionWithdraw);
});

test('DNI, comprobante y link quedan en su propio bloque', () => {
    assert.equal(guidance.place('No se pudieron obtener datos de RENIEC.', 200, 'identity').text, guidance.copy.dniMiss);
    assert.equal(guidance.place('No se pudieron obtener datos de RENIEC.', 200, 'identity').domain, 'identity');
    assert.equal(guidance.place('Los links deben usar HTTPS.', 422, 'documents').text, guidance.copy.linkInvalid);
    assert.equal(guidance.place('Usa JPG, PNG o PDF de hasta 8 MB.', 422, 'documents').domain, 'documents');
    assert.equal(guidance.invalidProof({ type: 'image/gif', size: 100 }), true);
    assert.equal(guidance.invalidProof({ type: 'application/pdf', size: 8 * 1024 * 1024 + 1 }), true);
    assert.equal(guidance.invalidProof({ type: 'image/png', size: 1000 }), false);
});

test('retiro, devolución, adicional y fuera de horario usan una línea operativa', () => {
    assert.match(guidance.copy.withdrawalHelp, /llegó pero se va antes/);
    assert.match(guidance.copy.withdrawalHelp, /nunca llegó/);
    assert.equal(guidance.copy.withdrawalDone, 'Retiro registrado. El horario quedó libre.');
    assert.match(guidance.copy.refundHint, /todavía no se devuelve/);
    assert.match(guidance.copy.additional, /paciente extra/);
    assert.match(guidance.copy.offHours, /fuera del horario configurado/);
    assert.match(source('resources/views/scheduling/agenda/partials/withdrawal.blade.php'), /Retiro y seguimiento/);
    assert.match(source('resources/views/scheduling/agenda/partials/withdrawal.blade.php'), /agenda-withdrawal-refund-hint/);
});

test('la contingencia dice qué hacer y no muestra un id interno', () => {
    const text = guidance.contingency('Ana Pérez', '23/11/2026 10:00');
    assert.match(text, /El horario del médico cambió/);
    assert.match(text, /Ana Pérez/);
    assert.match(text, /23\/11\/2026 10:00/);
    assert.match(text, /Contacta al paciente/);
    assert.doesNotMatch(text, /#\d+|appointment_id|debugging/i);
    assert.match(guidance.copy.resolveNote, /nueva hora, no contesta/);
});

test('un error de adelanto abre el bloque, enfoca el importe y no crea un modal', async () => {
    const elements = new Map();
    const focused = [];
    const element = () => ({
        hidden: true, value: '', checked: false, disabled: false, open: false, files: [], dataset: {}, classList: { add() {}, remove() {} },
        addEventListener() {}, focus() { focused.push(this); }, scrollIntoView() {}, replaceChildren() {},
    });
    const method = element(); method.value = 'EFECTIVO';
    const amount = element(); amount.value = '0';
    elements.set('agenda-op-method', method);
    elements.set('agenda-op-amount', amount);
    const document = {
        getElementById(id) { if (!elements.has(id)) { elements.set(id, element()); } return elements.get(id); },
        querySelector() { return { content: 'csrf' }; },
        createElement: element,
    };
    const context = { window: { AgendaGuidance: guidance, setTimeout, crypto: { randomUUID: () => '00000000-0000-4000-8000-000000000009' } }, document, setTimeout, clearTimeout, FormData: class { append() {} }, fetch: async () => ({ ok: false, status: 422, json: async () => ({ message: 'Para agendar se requiere adelanto real de al menos 50%.' }) }) };
    context.window.document = document;
    vm.runInNewContext(source('public/js/scheduling/agenda-operational-form.js'), context);
    vm.runInNewContext(source('public/js/scheduling/agenda-operational-workspace.js'), context);
    context.window.AgendaOperationalForm = context.AgendaOperationalForm;
    const workspace = context.window.AgendaOperationalWorkspace({
        endpoint: '/registrations', base: '/appointments', patientTemplate: '/patients/__PATIENT__', canCreate: true,
        context: () => ({ tipo_contexto: 'slot_libre', seleccionable: true, doctor_id: 1, fecha: '2026-10-09', hora_inicio: '10:00', minutos: 20, site_id: 1 }),
        payload: () => ({ patient_id: 1, doctor_id: 1, service_id: 1, site_id: 1, fecha_cita: '2026-10-09', hora_cita: '10:00', duracion_cita: 20 }),
        error: (body) => (body && body.message) || 'sin-mensaje', notice() {}, busy() {}, registered() {},
    });
    await workspace.register({ pending: false, patientId: 1, serviceId: 1, ownerId: '' });
    const panel = document.getElementById('agenda-op-payment-panel');
    const amountField = document.getElementById('agenda-op-amount');
    const error = document.getElementById('agenda-op-payment-error');
    assert.equal(panel.open, true);
    assert.equal(focused.includes(amountField), true);
    assert.equal(error.hidden, false);
    assert.equal(error.textContent, guidance.copy.insufficientAdvance);
    assert.equal(elements.has('agenda-guidance-modal'), false);
});
