const test=require('node:test');
const assert=require('node:assert/strict');
const {bookingIndicator,bookingLabel}=require('../../public/js/scheduling/admission-board.js');

const doc={createElement(tag){return {tagName:tag,style:{},attributes:{},setAttribute(name,value){this.attributes[name]=value;}};}};

test('main table hides the legacy booking chip visually and accessibly while preserving its footprint',()=>{
    const row={estado_agenda:'LEGADO',estado_cita:'ATENDIDO'};
    const before=JSON.stringify(row),chip=bookingIndicator(doc,row.estado_agenda);
    assert.equal(chip.style.visibility,'hidden');
    assert.equal(chip.attributes['aria-hidden'],'true');
    assert.equal(chip.className,'admission-tag admission-tag--booking');
    assert.equal(chip.style.display,undefined); // No removal from layout: current row dimensions stay intact.
    assert.equal(JSON.stringify(row),before);
});

test('pending reservations, confirmations and unknown actionable booking states remain visible',()=>{
    for(const state of ['PENDIENTE_CONFIRMACION','CONFIRMADA','BLOQUEO_REVISAR']){
        const chip=bookingIndicator(doc,state);
        assert.notEqual(chip.style.visibility,'hidden');
        assert.notEqual(chip.attributes['aria-hidden'],'true');
        assert.equal(chip.textContent,bookingLabel(state));
    }
    assert.match(bookingIndicator(doc,'PENDIENTE_CONFIRMACION').textContent,/Reserva pendiente.*sin confirmar/);
});

test('legacy state and the full label used in appointment details keep their original meaning',()=>{
    assert.equal(bookingLabel('LEGADO'),'Agenda heredada');
    assert.equal(bookingIndicator(doc,'LEGADO').textContent,'Agenda heredada');
});
