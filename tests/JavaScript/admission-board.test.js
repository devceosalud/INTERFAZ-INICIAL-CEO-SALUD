const {test}=require('node:test');
const assert=require('node:assert/strict');
const {latest,bookingLabel,time}=require('../../public/js/scheduling/admission-board.js');
test('pending booking is never labelled confirmed because payment or care changed',()=>{
    assert.match(bookingLabel('PENDIENTE_CONFIRMACION'),/sin confirmar/);
    assert.equal(bookingLabel('CONFIRMADA'),'Horario confirmado');
    assert.equal(bookingLabel('LEGADO'),'Agenda heredada');
    assert.notEqual(bookingLabel('CONFIRMADO'),'Horario confirmado');
});
test('missing or invalid timestamps never manufacture a care time',()=>{
    assert.equal(time(null),'Sin registro');assert.equal(time('invalid'),'Sin registro');
    assert.equal(time('2026-10-14T15:35:00Z'),'10:35');
});
test('late patient selection and filter responses cannot overwrite latest panel',async()=>{
    const gate=latest(),commits=[];let resolveOld;
    const old=gate.run(()=>new Promise(resolve=>{resolveOld=resolve;}),value=>commits.push(value));
    await gate.run(async()=>({patient:'CURRENT',saldo:'50.00'}),value=>commits.push(value));
    resolveOld({patient:'PREVIOUS',saldo:'0.00'});assert.equal(await old,false);
    assert.deepEqual(commits,[{patient:'CURRENT',saldo:'50.00'}]);
});
test('clearing detail prevents a late private appointment response from reappearing',async()=>{
    const gate=latest();let complete;let painted=false;
    const pending=gate.run(()=>new Promise(resolve=>{complete=resolve;}),()=>{painted=true;});
    gate.cancel();complete('old');assert.equal(await pending,false);assert.equal(painted,false);
});
