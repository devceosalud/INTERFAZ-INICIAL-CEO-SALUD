const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const guidance = require('../../public/js/scheduling/agenda-guidance');

const root = path.resolve(__dirname, '../..');
function source(file) { return fs.readFileSync(path.join(root, file), 'utf8'); }

test('comprobante se puede seleccionar en Documentos sin abrir ni registrar adelanto', () => {
    const view = source('resources/views/scheduling/agenda/partials/operational-registration.blade.php');
    const documents = view.slice(view.indexOf('id="agenda-op-documents-panel"'));
    assert.match(documents,/id="agenda-op-proof"[^>]*type="file"/);
    assert.equal((view.match(/id="agenda-op-proof"/g)||[]).length,1);
});

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
    assert.equal(guidance.copy.reserveSaved, 'Reserva guardada. Pendiente de adelanto.');
    assert.equal(guidance.copy.reserveSavedHere, 'Reserva guardada para esta hora.');
    assert.equal(guidance.copy.appointmentSaved, 'Cita confirmada.');
    assert.equal(guidance.copy.additionalSaved, 'Cita adicional creada.');
    assert.equal(guidance.copy.needSelection, 'Selecciona paciente, servicio y horario.');
    assert.equal(guidance.copy.needReservePatient, 'Selecciona el paciente de esta reserva.');
    assert.equal(guidance.copy.needAppointment, 'Primero guarda la reserva o selecciona una cita.');
    assert.equal(guidance.copy.needDoctorHour, 'Selecciona una hora dentro del horario del médico.');
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
    assert.equal(guidance.place('This action is unauthorized.', 403, 'payment').domain, 'payment');
    assert.equal(guidance.place('This action is unauthorized.', 403, 'withdraw').text, guidance.copy.permissionWithdraw);
    assert.equal(guidance.place('El dueño comercial se asigna al usuario autenticado.', 403, 'payment').text, 'El dueño comercial se asigna al usuario autenticado.');
    assert.equal(guidance.place('El dueño comercial se asigna al usuario autenticado.', 403, 'payment').domain, 'local');
    const additional = guidance.place('This action is unauthorized.', 403, 'additional');
    assert.equal(additional.text, guidance.copy.permissionAdditional);
    assert.equal(additional.domain, 'local');
    assert.equal(guidance.place('The motivo field is required.', 422, 'withdraw').text, guidance.copy.needWithdrawReason);
    assert.doesNotMatch(guidance.place('The motivo field is required.', 422, 'withdraw').text, /required|The /);
    assert.equal(guidance.place('Confirma que el paciente estuvo en la clínica. RETIRO no es NO ASISTIÓ.', 422, 'withdraw').text, guidance.copy.needPresence);
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
    assert.match(source('resources/views/scheduling/agenda/partials/withdrawal.blade.php'), /Estado de cita y seguimiento/);
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

test('otra reserva con paciente y servicio pide RESERVE y no borra el documento', () => {
    const actions = require('../../public/js/scheduling/agenda-appointment-actions');
    const selected = { tipo_contexto: 'cita_existente', hora_inicio: '10:00', fecha: '2026-10-09', doctor_id: 4, site_id: 1 };
    const intent = actions.reserveIntent(selected, '15', '3');
    assert.equal(intent.post, true);
    assert.equal(intent.pending, true);
    assert.equal(intent.bookingType, 'REGULAR');
    assert.equal(intent.occupiedHour, true);
    assert.equal(actions.reserveIntent(selected, '', '3').post, false);
    assert.equal(actions.reserveIntent(selected, '', '3').reason, 'patient');
    const file = source('public/js/scheduling/agenda.js');
    const handler = file.slice(
        file.indexOf("pendingStart.addEventListener('click'"),
        file.indexOf("additionalStart.addEventListener('click'")
    );
    assert.match(handler, /responsibleSelect\.value = ''/);
    assert.match(handler, /createAppointment\(true\)/);
    assert.match(handler, /needReservePatient/);
    assert.match(handler, /matchesDocument/);
    assert.doesNotMatch(handler, /documentNumber\.value = ''/);
});

test('cita adicional no borra al paciente y el éxito no dice solo cita agendada', () => {
    const handler = source('public/js/scheduling/agenda.js');
    const start = handler.indexOf("additionalStart.addEventListener('click'");
    const slice = handler.slice(start, handler.indexOf('async function moveAppointment'));
    assert.doesNotMatch(slice, /lookupModel\.blank|documentNumber\.value = ''/);
    assert.match(slice, /saved\.document/);
    assert.match(slice, /additionalMode = true/);
    assert.match(handler, /additionalSaved/);
    assert.equal(guidance.copy.additionalSaved, 'Cita adicional creada.');
});

test('reprogramar a otra fecha nombra el destino y ofrece verlo si no está en el día', () => {
    const actions = require('../../public/js/scheduling/agenda-appointment-actions');
    assert.equal(guidance.rescheduled('09/10/2026', '11:20'), 'Cita reprogramada para 09/10/2026 a las 11:20.');
    assert.equal(actions.showsDestination('2026-10-07', 'dia', '2026-10-07'), true);
    assert.equal(actions.showsDestination('2026-10-07', 'dia', '2026-10-08'), false);
    const agenda = source('public/js/scheduling/agenda.js');
    assert.match(agenda, /agenda-see-destination/);
    assert.match(agenda, /showsDestination\(state\.date, state\.view, date\)/);
    assert.match(agenda, /showsDestination\(state\.date, state\.view, destination\.fecha_cita\)/);
    assert.match(source('resources/views/scheduling/agenda/partials/quick-registration.blade.php'), /Ver nueva fecha/);
});

test('un guardado exitoso retira el aviso de error anterior', () => {
    const agenda = source('public/js/scheduling/agenda.js');
    const registered = agenda.slice(agenda.indexOf('registered: async'), agenda.indexOf('const pendingStart'));
    assert.match(registered, /agenda-action-notice/);
    assert.match(registered, /notice\.hidden = true/);
    assert.match(registered, /classList\.remove\('is-alert'\)/);
    assert.match(agenda, /if \(outcome && !outcome\.cancelled\) \{\s*const box = document\.getElementById\('agenda-reschedule-error'\);\s*if \(box\) \{ box\.hidden = true; box\.textContent = ''; \}/);
});

test('documentos vacíos y adelanto cero no anuncian un guardado', async () => {
    const elements = new Map();
    const notices = [];
    const element = () => {
        const node = {
            hidden: true, value: '', checked: false, disabled: false, open: false, files: [], dataset: {}, textContent: '',
            classList: { add() {}, remove() {} }, listeners: {},
            addEventListener(name, fn) { node.listeners[name] = fn; },
            focus() {}, scrollIntoView() {}, replaceChildren() {},
        };
        return node;
    };
    elements.set('agenda-op-method', Object.assign(element(), { value: 'EFECTIVO' }));
    elements.set('agenda-op-amount', Object.assign(element(), { value: '0' }));
    elements.set('agenda-op-links', Object.assign(element(), { value: '' }));
    elements.set('agenda-op-proof', Object.assign(element(), { files: [] }));
    const document = {
        getElementById(id) { if (!elements.has(id)) { elements.set(id, element()); } return elements.get(id); },
        querySelector() { return { content: 'csrf' }; },
        createElement: element,
    };
    let requested = false;
    const context = {
        window: { AgendaGuidance: guidance, setTimeout, crypto: { randomUUID: () => '00000000-0000-4000-8000-000000000010' } },
        document, setTimeout, clearTimeout, FormData: class { append() {} },
        fetch: async () => { requested = true; return { ok: true, status: 201, json: async () => ({}) }; },
    };
    context.window.document = document;
    vm.runInNewContext(source('public/js/scheduling/agenda-operational-form.js'), context);
    vm.runInNewContext(source('public/js/scheduling/agenda-operational-workspace.js'), context);
    context.window.AgendaOperationalForm = context.AgendaOperationalForm;
    context.window.AgendaOperationalWorkspace({
        endpoint: '/registrations', base: '/appointments', patientTemplate: '/patients/__PATIENT__', canCreate: true,
        context: () => ({ tipo_contexto: 'cita_existente', appointment_id: 9, estado_agenda: 'PENDIENTE_CONFIRMACION' }),
        payload: () => ({ patient_id: 1 }),
        error: (body) => (body && body.message) || 'sin-mensaje',
        notice(message) { notices.push(message); }, busy() {}, registered() {}, paymentUpdated() {},
    });
    await document.getElementById('agenda-op-submit-payment').listeners.click();
    await document.getElementById('agenda-op-add-documents').listeners.click();
    assert.equal(requested, false);
    assert.equal(document.getElementById('agenda-op-payment-error').textContent, guidance.copy.needAmount);
    assert.equal(document.getElementById('agenda-op-documents-error').textContent, guidance.copy.needDocument);
    assert.equal(notices.some((message) => /registrado|guardados/i.test(message)), false);
    document.getElementById('agenda-op-proof').files = [{ type: 'image/png', size: 100 }];
    await document.getElementById('agenda-op-add-documents').listeners.click();
    assert.equal(document.getElementById('agenda-op-documents-error').hidden, true);
    assert.equal(document.getElementById('agenda-op-documents-status').textContent, guidance.copy.fileSaved);
});

test('un 403 de cita adicional no abre el pago', async () => {
    const elements = new Map();
    const notices = [];
    const element = () => ({
        hidden: true, value: '', checked: false, disabled: false, open: false, files: [], dataset: {}, textContent: '',
        classList: { add() {}, remove() {} },
        addEventListener() {}, focus() {}, scrollIntoView() {}, replaceChildren() {},
    });
    const method = element(); method.value = 'EFECTIVO';
    elements.set('agenda-op-method', method);
    elements.set('agenda-op-amount', Object.assign(element(), { value: '0' }));
    const document = {
        getElementById(id) { if (!elements.has(id)) { elements.set(id, element()); } return elements.get(id); },
        querySelector() { return { content: 'csrf' }; },
        createElement: element,
    };
    const context = {
        window: { AgendaGuidance: guidance, setTimeout, crypto: { randomUUID: () => '00000000-0000-4000-8000-000000000011' } },
        document, setTimeout, clearTimeout, FormData: class { append() {} },
        fetch: async () => ({ ok: false, status: 403, json: async () => ({ message: 'This action is unauthorized.' }) }),
    };
    context.window.document = document;
    vm.runInNewContext(source('public/js/scheduling/agenda-operational-form.js'), context);
    vm.runInNewContext(source('public/js/scheduling/agenda-operational-workspace.js'), context);
    context.window.AgendaOperationalForm = context.AgendaOperationalForm;
    const workspace = context.window.AgendaOperationalWorkspace({
        endpoint: '/registrations', base: '/appointments', patientTemplate: '/patients/__PATIENT__', canCreate: true,
        context: () => ({ tipo_contexto: 'slot_libre', seleccionable: true, doctor_id: 1, fecha: '2026-10-09', hora_inicio: '10:00', minutos: 20, site_id: 1 }),
        payload: () => ({ patient_id: 2, doctor_id: 1, service_id: 1, site_id: 1, fecha_cita: '2026-10-09', hora_cita: '10:00', duracion_cita: 20 }),
        error: (body) => (body && body.message) || 'sin-mensaje',
        notice(message) { notices.push(message); }, busy() {}, registered() {},
    });
    await workspace.register({ pending: false, patientId: 2, serviceId: 1, ownerId: '', bookingType: 'ADICIONAL' });
    assert.equal(document.getElementById('agenda-op-payment-panel').open, false);
    assert.equal(notices.at(-1), guidance.copy.permissionAdditional);
});

test('la ayuda clickeable reemplaza el símbolo de información en lo obvio', () => {
    const legend = source('resources/views/scheduling/agenda/index.blade.php');
    const calendar = source('resources/views/scheduling/agenda/partials/mini-calendar.blade.php');
    const withdrawal = source('resources/views/scheduling/agenda/partials/withdrawal.blade.php');
    assert.match(legend, /PENDIENTE_CONFIRMACION', 'ADICIONAL', 'FUERA_HORARIO'/);
    assert.doesNotMatch(legend, /ⓘ/);
    assert.doesNotMatch(calendar, /ⓘ/);
    assert.doesNotMatch(withdrawal, /ⓘ/);
    assert.match(legend, /Minutos todavía disponibles dentro del horario del médico/);
    assert.match(legend, /Citas adicionales y atenciones fuera de horario/);
    assert.match(calendar, /El color indica cuánto del horario regular ya está confirmado con adelantos/);
    assert.match(withdrawal, /No asistió: nunca llegó/);
});
