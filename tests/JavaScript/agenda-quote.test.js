const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const settle = () => new Promise(resolve => setImmediate(resolve));

function workspace() {
    const elements = new Map(), pending = [], calls = [],asks=[];let decision=true;
    const element = () => ({ value: '', hidden: true, disabled: false, checked: false, files: [], dataset: {}, handlers: {},
        addEventListener(name, handler) { this.handlers[name] = handler; }, replaceChildren() {},
        classList: { add() {}, remove() {} }, focus() {}, setAttribute() {}, scrollIntoView() {} });
    const document = { getElementById(id) { if (!elements.has(id)) elements.set(id, element()); return elements.get(id); },
        querySelector() { return { content: 'test-csrf' }; }, createElement: element };
    let patientId = 2, serviceId = 3;
    let selected = { tipo_contexto: 'slot_libre', doctor_id: 1, fecha: '2026-10-13', hora_inicio: '09:00' };
    const sandbox = { document, AbortController, setTimeout:(fn,ms)=>{const timer=setTimeout(fn,ms);timer.unref();return timer;}, clearTimeout, FormData:class {constructor(){this.values=new Map();}append(k,v){this.values.set(k,v);}},
        window: {Swal:{fire:async options=>{asks.push(options.text);return {isConfirmed:decision};}},confirm:message=>{asks.push(message);return decision;},crypto:{randomUUID:()=> '11111111-1111-4111-a111-111111111111'},AgendaGuidance: require('../../public/js/scheduling/agenda-guidance') },
        fetch: (url, options = {}) => {
            calls.push({ url, options });
            if (url === '/quote') return new Promise((resolve,reject) => {pending.push(resolve);options.signal?.addEventListener('abort',()=>{const error=new Error('Aborted');error.name='AbortError';reject(error);});});
            const body = url.endsWith('/reevaluation-indications')?{indication_id:42}:url.endsWith('/economy') ? { precio: '175.00', saldo: '175.00', pago_real: '0.00' }
                : url.endsWith('/documents') ? { documents: [] } : { patient: { telefono: '' } };
            return Promise.resolve({ ok: true, status: 200, json: async () => body });
        } };
    for (const name of ['agenda-operational-form', 'agenda-operational-workspace']) {
        vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/scheduling/' + name + '.js'), 'utf8'), sandbox);
        if (name === 'agenda-operational-form') sandbox.window.AgendaOperationalForm = sandbox.AgendaOperationalForm;
    }
    const widget = sandbox.window.AgendaOperationalWorkspace({endpoint:'/register',payload:()=>({doctor_id:1,service_id:3,patient_id:2,duracion_cita:30}),registered:async()=>{},base: '/appointments', quoteEndpoint: '/quote',
        patientTemplate: '/patients/__PATIENT__', context: () => selected, canCreate: true, notice() {}, error: b => b.message,
        quoteData: () => ({ doctor_id: selected.doctor_id, service_id: serviceId, patient_id: patientId, fecha_cita: selected.fecha }) });
    return { widget, pending, calls, asks,decision(v){decision=v;},field: id => document.getElementById('agenda-op-' + id),
        select(value) { selected = value; }, patient(value) { patientId = value; }, service(value) { serviceId = value; }, answer(index, price, ok = true, rule = 'NORMAL') {
            pending[index]({ ok, status: ok ? 200 : 422, json: async () => ok ? { precio_programado: price, pricing_rule:rule } : { message: 'Tarifa no vigente' } });
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
    assert.equal(qa.field('quote-status').hidden, false); assert.equal(qa.field('quote-status').textContent, 'No se pudo cotizar: Tarifa no vigente');
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


test('Agenda cotiza descuento neto, invalida respuesta anterior y bloquea editar descuentos persistidos', async () => {
    const qa=workspace();
    qa.field('discount-type').value='FIXED';qa.field('discount-value').value='30';qa.widget.quoteChanged(100);
    assert.deepEqual(JSON.parse(qa.calls[0].options.body).discount,{type:'FIXED',value:'30',reason:null});
    qa.field('discount-value').value='20';qa.field('discount-value').handlers.input();
    qa.answer(1,'80.00');await settle();qa.answer(0,'70.00');await settle();assert.equal(qa.widget.readiness().price,'80.00');
    const selected={tipo_contexto:'cita_existente',appointment_id:5,patient_id:2,estado_agenda:'CONFIRMADA'};
    qa.select(selected);await qa.widget.selectionChanged(selected);assert.equal(qa.field('discount-type').disabled,true);
    assert.equal(qa.field('discount-value').disabled,true);
});

test('indicación y referido explícitos viajan al servidor; gratuidad solo desde cotización vigente',async()=>{
    const qa=workspace();qa.field('indication').value='42';qa.field('referred').checked=true;qa.field('referral-origin').value='QA origen verificable';
    qa.widget.quoteChanged(150);
    const data=JSON.parse(qa.calls[0].options.body);
    assert.equal(data.reevaluation_indication_id,42);assert.deepEqual(data.referral,{origin:'QA origen verificable',attested:true});
    assert.equal(qa.widget.readiness().automaticFree,false);
    qa.answer(0,'0.00',true,'SAME_DAY_REEVALUATION');await settle();assert.equal(qa.widget.readiness().automaticFree,true);
    qa.field('indication').value='43';qa.field('indication').handlers.input();assert.equal(qa.widget.readiness().automaticFree,false);
    qa.answer(1,'220.00');await settle();assert.equal(qa.widget.readiness().automaticFree,false);assert.equal(qa.widget.readiness().price,'220.00');
});

test('registro de indicación escrita envía evidencia y atestación, nunca importes',async()=>{
    const qa=workspace();const selected={tipo_contexto:'cita_existente',appointment_id:5,patient_id:2,estado_agenda:'LEGADO'};
    qa.select(selected);await qa.widget.selectionChanged(selected);
    qa.field('indication-document').value='11';qa.field('indication-date').value='2026-10-13';qa.field('indication-evidence').value='WRITTEN_ORDER';
    qa.field('indication-reference').value='Folio QA 1';qa.field('indication-attested').checked=true;
    await qa.field('record-indication').handlers.click();
    const call=qa.calls.find(c=>c.url.endsWith('/reevaluation-indications'));
    const data=JSON.parse(call.options.body);assert.equal(data.evidence_document_id,11);assert.equal(data.doctor_order_attested,true);
    assert.equal(data.instruction_reference,'Folio QA 1');assert.equal(data.payment,undefined);assert.equal(data.precio_programado,undefined);
});
test('subida de orden privada usa multipart y limpiar contexto elimina evidencia del anterior',async()=>{
    const qa=workspace();const selected={tipo_contexto:'cita_existente',appointment_id:5,patient_id:2,estado_agenda:'LEGADO'};
    qa.select(selected);await qa.widget.selectionChanged(selected);
    const file={type:'image/png',size:120};qa.field('indication-file').files=[file];
    qa.field('indication-file').value='qa.png';qa.field('indication-attested').checked=true;
    qa.field('indication-reference').value='Folio QA 2';qa.field('indication-date').value='2026-10-13';
    await qa.field('record-indication').handlers.click();
    const call=qa.calls.find(c=>c.url.endsWith('/reevaluation-indications'));
    assert.equal(call.options.body.values.get('file'),file);
    const data=JSON.parse(call.options.body.values.get('payload'));assert.equal(data.evidence_document_id,undefined);
    const next={tipo_contexto:'cita_existente',appointment_id:6,patient_id:3,estado_agenda:'LEGADO'};
    qa.select(next);await qa.widget.selectionChanged(next);
    assert.equal(qa.field('indication-file').value,'');assert.equal(qa.field('indication-attested').checked,false);
    assert.equal(qa.field('indication-date').value,'');assert.equal(qa.field('indication-reference').value,'');
});

test('sin datos pendientes cambia directamente; con cambios permite cancelar sin borrar el formulario',async()=>{
    const qa=workspace();const first={tipo_contexto:'slot_libre',doctor_id:1,fecha:'2026-10-13',hora_inicio:'09:00'};
    qa.select(first);await qa.widget.selectionChanged(first);
    const next={...first,hora_inicio:'10:00'};
    assert.equal(await qa.widget.prepareSelection(next),true);assert.equal(qa.asks.length,0);
    qa.field('reason').value='Dato ficticio sin guardar';qa.decision(false);
    assert.equal(await qa.widget.prepareSelection(next),false);assert.equal(qa.field('reason').value,'Dato ficticio sin guardar');
    assert.equal(qa.asks.length,1);qa.decision(true);assert.equal(await qa.widget.prepareSelection(next),true);
    qa.select(next);await qa.widget.selectionChanged(next);assert.equal(qa.field('reason').value,'');assert.equal(qa.widget.hasUnsavedChanges(),false);
});
test('descartar limpia contextos de dinero, descuentos, indicación y contactos del intervalo anterior',async()=>{
    const qa=workspace();const first={tipo_contexto:'slot_libre',doctor_id:1,fecha:'2026-10-13',hora_inicio:'09:00'};
    qa.select(first);await qa.widget.selectionChanged(first);
    Object.assign(qa.field('amount'),{value:'50'});qa.field('method').value='YAPE';qa.field('operation').value='QA-OLD';qa.field('origin').value='YAPE';
    qa.field('phone').value='999000123';qa.field('discount-type').value='FIXED';qa.field('discount-value').value='10';
    qa.field('indication').value='42';qa.field('double').checked=true;qa.field('postoperative').checked=true;
    const next={...first,doctor_id:2,hora_inicio:'10:00'};assert.equal(await qa.widget.prepareSelection(next),true);
    qa.select(next);await qa.widget.selectionChanged(next);
    for(const name of ['operation','origin','phone','indication','discount-type'])assert.equal(qa.field(name).value,'',name);
    assert.equal(qa.field('amount').value,'0');assert.equal(qa.field('method').value,'EFECTIVO');
    assert.equal(qa.field('double').checked,false);assert.equal(qa.field('postoperative').checked,false);
    assert.equal(qa.field('price').value,'0.00');assert.equal(qa.field('paid').value,'0.00');
});
test('una actualización del mismo contexto conserva notas todavía sin guardar',async()=>{
    const qa=workspace();const selected={tipo_contexto:'cita_existente',appointment_id:5,patient_id:2,estado_agenda:'CONFIRMADA'};
    qa.select(selected);await qa.widget.selectionChanged(selected);qa.field('reason').disabled=false;
    qa.field('reason').value='Corrección ficticia pendiente';await qa.widget.selectionChanged(selected);
    assert.equal(qa.field('reason').value,'Corrección ficticia pendiente');
});
test('selección del calendario consulta el aviso antes de modificar la cita elegida',async()=>{
    const source=fs.readFileSync(path.join(__dirname,'../../public/js/scheduling/agenda.js'),'utf8');
    const fn=source.slice(source.indexOf('    async function selectInterval('),source.indexOf('    function applyQuickState('));
    const original={appointment_id:5},next={appointment_id:6};let asks=0;
    const sandbox={state:{selection:original},operationalWorkspace:{prepareSelection(c){assert.equal(c,next);asks++;return false;}}};
    vm.runInNewContext(fn+'\nglobalThis.choose=selectInterval;',sandbox);
    assert.equal(await sandbox.choose(next,null),false);assert.equal(sandbox.state.selection,original);assert.equal(asks,1);
});
test('doble duración se envía explícita, ocupa dos intervalos y se desactiva al cambiar de horario',async()=>{
    const qa=workspace();qa.widget.quoteChanged(100);qa.answer(0,'100.00');await settle();
    qa.field('double').checked=true;qa.field('reason').value='Motivo ficticio válido';
    await qa.widget.register({patientId:2,serviceId:3,bookingType:'REGULAR',pending:true});
    const call=qa.calls.find(c=>c.url==='/register');const payload=JSON.parse(call.options.body.values.get('payload'));
    assert.equal(payload.cita_doble,true);assert.equal(payload.duracion_cita,60);
    const next={tipo_contexto:'slot_libre',doctor_id:1,fecha:'2026-10-13',hora_inicio:'10:00'};
    qa.select(next);await qa.widget.selectionChanged(next);assert.equal(qa.field('double').checked,false);
});

test('abrir ficha sin editar no marca cambios; modificar y revertir compara los datos reales',()=>{
 const source=fs.readFileSync(path.join(__dirname,'../../public/js/scheduling/agenda.js'),'utf8');
 const helper=source.slice(source.indexOf('    let draftBaseline'),source.indexOf('    function paintDraft'));
 const sandbox={state:{draft:null},draftModel:{toPayload:d=>({nombre:d.nombre})}};
 vm.runInNewContext(helper+'\nglobalThis.changed=draftChanged;globalThis.open=(name)=>{state.draft={nombre:name};draftBaseline=JSON.stringify(draftModel.toPayload(state.draft));};',sandbox);
 assert.equal(sandbox.changed(),false);sandbox.open('Paciente ficticio');assert.equal(sandbox.changed(),false);
 sandbox.state.draft.nombre='Cambio pendiente';assert.equal(sandbox.changed(),true);
 sandbox.state.draft.nombre='Paciente ficticio';assert.equal(sandbox.changed(),false);
});

for(const dimension of ['context','document']){
 test('la búsqueda tardía de paciente no hidrata otro '+dimension,async()=>{
  const source=fs.readFileSync(path.join(__dirname,'../../public/js/scheduling/agenda.js'),'utf8');
  const handler=source.slice(source.indexOf("    el.patientLookup.addEventListener('submit'"),source.indexOf("    el.patientDraft.addEventListener('submit'"));
  let submit,answer;const painted=[];
  const el={patientLookup:{dataset:{endpoint:'/lookup'},addEventListener(name,fn){submit=fn;}},documentType:{value:'DNI'},
   documentNumber:{value:'70000001'},documentSearch:{disabled:false},lookupResult:{}};
  const state={selection:{doctor_id:1,hora_inicio:'09:00'},draft:null};
  const sandbox={el,state,operationalWorkspace:{contextKey:c=>JSON.stringify(c)},scheduleSnapshot:()=>({}),
   paintIdentity:value=>painted.push(value),lookupModel:{edited:()=>({status:'searching'}),present:()=>({status:'found',patientId:7})},
   document:{querySelector:()=>({content:'test-csrf'})},fetch:()=>new Promise(resolve=>answer=resolve)};
  vm.runInNewContext(handler,sandbox);
  const pending=submit({preventDefault(){}});
  if(dimension==='context')state.selection={doctor_id:2,hora_inicio:'10:00'};else el.documentNumber.value='70000002';
  answer({ok:true,json:async()=>({patient_id:7})});await pending;
  assert.equal(painted.length,1);assert.equal(painted[0].status,'searching');assert.equal(el.documentSearch.disabled,false);
 });
}
