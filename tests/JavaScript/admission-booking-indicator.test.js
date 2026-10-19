const test=require('node:test');
const assert=require('node:assert/strict');
const {bookingIndicator,bookingLabel}=require('../../public/js/scheduling/admission-board.js');

const doc={createElement(tag){return {tagName:tag,style:{},attributes:{},setAttribute(name,value){this.attributes[name]=value;}};}};

test('main table removes the legacy booking chip without mutating its stored state',()=>{
    const row={estado_agenda:'LEGADO',estado_cita:'ATENDIDO'},before=JSON.stringify(row);
    assert.equal(bookingIndicator(doc,row.estado_agenda),null);assert.equal(JSON.stringify(row),before);
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
    assert.equal(bookingIndicator(doc,'LEGADO'),null);
});
