const test = require('node:test');
const assert = require('node:assert/strict');
const form = require('../../public/js/scheduling/agenda-operational-form');
test('edición parcial no borra por controles vacíos y conserva solo cambios explícitos', () => {
    assert.deepEqual(form.nonBlank({ telefono: '', channel_id: null, observaciones: '   ', motivo_consulta: 'Control', interaction_medium_id: 7 }),
        { motivo_consulta: 'Control', interaction_medium_id: 7 });
});
test('importe con centavos exactos y efectivo sin número inventado', () => {
    assert.equal(form.cents('0.10'), 10);
    assert.equal(form.cents('150.05'), 15005);
    assert.equal(form.decimal(999), '9.99');
    assert.deepEqual(form.payment('50', 'EFECTIVO', 'DO-NOT-KEEP', ''), { amount: '50.00', method: 'EFECTIVO', operation: null, origin: null });
    for (const value of ['-1', '1e3', '1.001', 'AUTORIZA DR QUIROZ']) { assert.throws(() => form.cents(value)); }
    assert.throws(() => form.payment('50', 'YAPE', '', ''));
    assert.equal(form.payment('50', 'YAPE', 'LOCAL-OP', '').operation, 'LOCAL-OP');
});
test('links múltiples HTTPS con etiquetas y sin descarga automática', () => {
    assert.deepEqual(form.links('Pago | https://drive.google.com/a\nhttps://docs.google.com/b'), [
        { label: 'Pago', url: 'https://drive.google.com/a' }, { label: 'Documento externo 2', url: 'https://docs.google.com/b' },
    ]);
    assert.throws(() => form.links('http://google.com/unsafe'));
    assert.throws(() => form.links('javascript:alert(1)'));
    assert.throws(() => form.links(Array(11).fill('https://google.com').join('\n')));
});

test('confirmar regular explica permisos, selección, servicio y el mínimo exacto en centavos', () => {
    const ready = {canCreate:true,busy:false,slot:true,patient:true,service:true,canPay:true,price:'100.01',amount:'50.01'};
    assert.equal(form.bookingReadiness(ready).allowed,true);
    for (const [change, message] of [
        [{canCreate:false},/appointment.create/],[{busy:true},/Guardando/],[{slot:false},/intervalo/],
        [{patient:false},/paciente/],[{service:false},/servicio/],[{canPay:false},/appointment.payment.submit/],
        [{amount:'50.00'},/50.01/],[{amount:'101'},/supera/],[{price:'0'},/precio válido/],[{amount:'1e3'},/importe válido/],
    ]) {
        const result=form.bookingReadiness({...ready,...change}); assert.equal(result.allowed,false); assert.match(result.reason,message);
    }
    assert.equal(form.bookingReadiness({...ready,amount:'0',waived:true,canPay:false}).allowed,true);
    assert.equal(form.bookingReadiness({...ready,amount:'50',waived:true,canPay:false}).allowed,false);
    assert.equal(form.bookingReadiness({...ready,amount:'0',exception:true}).allowed,false);
    assert.equal(form.bookingReadiness({...ready,amount:'0'}).allowed,false);
});

test('guardar reserva exige selección completa y permite importe cero sin permiso financiero',()=>{
    const ready={canCreate:true,busy:false,slot:true,patient:true,service:true,canPay:false,price:'100',amount:'0'};
    assert.equal(form.reservationReadiness(ready).allowed,true);
    for(const [change,message] of [[{canCreate:false},/appointment.create/],[{busy:true},/Guardando/],[{slot:false},/intervalo/],
        [{patient:false},/paciente/],[{service:false},/servicio/],[{price:'0'},/precio válido/],[{amount:'101'},/supera/],
        [{amount:'50'},/appointment.payment.submit/],[{amount:'1e3'},/importe válido/]]){
        const result=form.reservationReadiness({...ready,...change});assert.equal(result.allowed,false);assert.match(result.reason,message);
    }
    assert.equal(form.reservationReadiness({...ready,canPay:true,amount:'49.99'}).allowed,true);
    assert.equal(form.bookingReadiness({...ready,canPay:true,amount:'49.99'}).allowed,false);
});

test('tarifa médica gratuita vigente permite reserva y confirmación sin permiso financiero, nunca admite dinero',()=>{
    const input={canCreate:true,busy:false,slot:true,patient:true,service:true,canPay:false,price:'0.00',amount:'0',automaticFree:true};
    assert.equal(form.reservationReadiness(input).allowed,true);assert.equal(form.bookingReadiness(input).allowed,true);
    assert.equal(form.bookingReadiness({...input,automaticFree:false}).allowed,false);
    assert.equal(form.bookingReadiness({...input,amount:'0.01'}).allowed,false);
    assert.equal(form.bookingReadiness({...input,canCreate:false}).allowed,false);
});

test('pago completo nunca implica horario confirmado ni atención realizada',()=>{
 const pending=form.stateSummary({estado_agenda:'PENDIENTE_CONFIRMACION',estado_cita:'PROGRAMADO',estado_pagado:'PAGADO'});
 assert.equal(pending.agenda,'RESERVA PENDIENTE');assert.equal(pending.care,'PROGRAMADO');assert.equal(pending.payment,'PAGADO');
 const confirmed=form.stateSummary({estado_agenda:'CONFIRMADA',estado_cita:'PROGRAMADO',estado_pagado:'PARCIAL'});
 assert.equal(confirmed.agenda,'CONFIRMADA');assert.equal(confirmed.care,'PROGRAMADO');assert.match(confirmed.description,/Pago: PARCIAL/);
 assert.equal(form.stateSummary({estado_pagado:'PAGADO'}).agenda,'AGENDA SIN ACREDITAR');
});
