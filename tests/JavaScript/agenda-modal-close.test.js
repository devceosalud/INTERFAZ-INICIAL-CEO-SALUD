const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const js = fs.readFileSync(path.resolve(__dirname, '../../public/js/scheduling/agenda.js'), 'utf8');
const css = fs.readFileSync(path.resolve(__dirname, '../../public/css/scheduling/agenda.css'), 'utf8');

function section(start, end) {
    const from = js.indexOf(start);
    const to = js.indexOf(end, from);
    assert.ok(from >= 0 && to > from, 'Expected real Agenda function not found');
    return js.slice(from, to);
}

test('confirmación SweetAlert2 se muestra encima del modal de paciente', () => {
    const custom = /body\.agenda-viewport \.swal2-container\s*\{\s*z-index:\s*(\d+)\s*!important;/m.exec(css);
    const modal = /\.agenda-patient-modal\s*\{[^}]*z-index:\s*(\d+)/m.exec(css);
    assert.ok(custom && modal, 'Both stacking layers must be defined');
    assert.ok(Number(custom[1]) > Number(modal[1]));
});

test('ocultar modal mueve el foco antes de marcar el formulario aria-hidden', () => {
    const actions = [];
    const closeButton = { id: 'agenda-draft-close' };
    const element = () => ({ hidden:false, open:false, prepend() {} });
    const elements = new Map();
    const patientModal = { hidden:false, contains: node => node === closeButton,
        setAttribute(name,value) { actions.push(['attribute',name,value]); } };
    const el = { patientModal, documentNumber:{focus(){actions.push(['focus','documentNumber']);}},
        draftMessage: element(), draftRuc:element(), draftReniec:element() };
    const document = { activeElement:closeButton, body:{classList:{toggle(){}}}, getElementById(id) {
        if (!elements.has(id)) elements.set(id,element());return elements.get(id);
    } };
    const context = {el,document,state:{draft:{open:true}},draftBaseline:'previous'};
    vm.runInNewContext(section('    function paintDraft(draft) {', '    function bindDraftField(')
        + '\nglobalThis.paint = paintDraft;', context);
    context.paint({open:false});
    assert.equal(patientModal.hidden,true);
    assert.deepEqual(actions[0],['focus','documentNumber']);
    assert.deepEqual(actions.at(-1),['attribute','aria-hidden','true']);
});

function modalCloseFixture() {
    const actions = [];
    let answer;
    const confirmation = new Promise(resolve => {answer=resolve;});
    const trigger = {isConnected:true,blur(){actions.push('blur');},focus(){actions.push('restore');}};
    const context = {
        state:{appointmentBusy:false,draft:{open:true},identity:null},
        el:{patientModal:{contains:node=>node===trigger},patientRegister:{hidden:false,disabled:false}},
        document:{activeElement:trigger},
        draftChanged:()=>true,
        operationalWorkspace:{confirmDiscard:async()=>{actions.push('confirm');return confirmation;}},
        paintDraft:()=>{actions.push('close');context.state.draft=null;},
        draftModel:{cancel:()=>({open:false})},
        scheduleSnapshot:()=>({})
    };
    vm.runInNewContext(section('    let closingPatientModal = false;', '    function patientValidationMessage(')
        + '\nglobalThis.close = closePatientModal;', context);
    return {context,actions,answer};
}

test('Cancelar solicita descarte una sola vez, recupera el foco al conservar cambios', async () => {
    const qa=modalCloseFixture();
    const first=qa.context.close();
    await qa.context.close();
    assert.deepEqual(qa.actions,['blur','confirm']);
    qa.answer(false);await first;
    assert.deepEqual(qa.actions,['blur','confirm','restore']);
    assert.equal(qa.context.state.draft.open,true);
});

test('Confirmar descarte cierra modal sin depender de un segundo clic', async () => {
    const qa=modalCloseFixture();
    const result=qa.context.close();
    qa.answer(true);await result;
    assert.deepEqual(qa.actions,['blur','confirm','close']);
    assert.equal(qa.context.state.draft,null);
});
