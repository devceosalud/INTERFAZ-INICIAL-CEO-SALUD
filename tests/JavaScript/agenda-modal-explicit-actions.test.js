'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const js = fs.readFileSync(path.resolve(__dirname, '../../public/js/scheduling/agenda.js'), 'utf8');
const view = fs.readFileSync(path.resolve(__dirname, '../../resources/views/scheduling/agenda/partials/patient-modal.blade.php'), 'utf8');
const workspace = fs.readFileSync(path.resolve(__dirname, '../../public/js/scheduling/agenda-operational-workspace.js'), 'utf8');

function section(start, end) {
    const from = js.indexOf(start);
    const to = js.indexOf(end, from);
    assert.ok(from >= 0 && to > from, 'Could not locate modal event handlers');
    return js.slice(from, to);
}
function button() {
    const handlers = {};
    return { handlers, addEventListener: (name, cb) => {handlers[name] = cb;} };
}
test('Ambos botones del popup son explícitos y Enter no es confirmación', () => {
    assert.match(view, /<button type="button"[^>]*id="agenda-draft-save"/);
    assert.match(view, /<button type="button"[^>]*id="agenda-draft-save-schedule"/);
    assert.match(workspace, /mode: options\.pending \? 'RESERVE' : 'CONFIRM'/);
    assert.match(js, /createAppointment\(!attachToSchedule, draftModel\.toPayload\(state\.draft\)/);
});
test('Guardar reserva y Confirmar envían rutas distintas; submit implícito no envía ninguna', () => {
    const el = {
        patientDraft:button(), patientRegister:button(), draftSave:button(), draftSaveSchedule:button(),
        draftMessage:{textContent:''}
    };
    const calls = [];
    const context = {
        el,
        state:{draft:{open:true}},
        savePatient:async(flag)=>calls.push(flag),
        draftModel:{open(){return {open:false};}},
        scheduleSnapshot(){return {};},
        closePatientModal(){},
    };
    const submit = section("    el.patientDraft.addEventListener('submit',", "    el.patientRegister.addEventListener('click',");
    const clicks = section("    el.draftSave.addEventListener('click',", "    el.appointmentSubmit.addEventListener('click',");
    vm.runInNewContext(submit+clicks,context);

    let prevented=false;
    el.patientDraft.handlers.submit({preventDefault(){prevented=true;}});
    assert.equal(prevented,true);
    assert.equal(calls.length,0);
    assert.match(el.draftMessage.textContent,/Guardar reserva/);

    el.draftSave.handlers.click();
    el.draftSaveSchedule.handlers.click();
    assert.deepEqual(calls,[false,true]);
});
