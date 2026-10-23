const test=require('node:test'),assert=require('node:assert/strict');
const {paymentProjection}=require('../../public/js/scheduling/agenda-operational-form');
const {groupRows}=require('../../public/js/scheduling/operational-reevaluations');
test('pago anterior y nuevo adelanto permanecen separados antes de guardar',()=>{
 const p={fuente:'PAYMENTS',precio:'120.00',pago_real:'60.00',saldo:'60.00'};
 assert.deepEqual(paymentProjection(p,'0'),{paidLabel:'Total pagado anteriormente',paid:'60.00',currentBalance:'60.00',advance:'0.00',balance:'60.00'});
 assert.equal(paymentProjection(p,'10').balance,'50.00');assert.equal(paymentProjection(p,'10').paid,'60.00');assert.equal(p.saldo,'60.00');
});
test('el snapshot histórico no se anuncia como pago acreditado y la atribución incierta no se suma',()=>{
 const historical=paymentProjection({fuente:'LEGACY_SNAPSHOT',pago_real:'60.00',saldo:'60.00'},'10');assert.match(historical.paidLabel,/no acredita pago/);
 const unknown=paymentProjection({fuente:'UNALLOCATED',pago_real:'0',saldo:'0'},'10');assert.equal(unknown.paid,null);assert.equal(unknown.currentBalance,null);assert.equal(unknown.balance,null);
});
test('proyección conserva centavos y el saldo que ya descuenta crédito sin inventar un pago',()=>{
 const p=paymentProjection({fuente:'PAYMENTS',pago_real:'60.00',saldo:'40.25'},'10.01');assert.equal(p.balance,'30.24');assert.equal(p.paid,'60.00');
 assert.throws(()=>paymentProjection({saldo:'60'},'-1'));
});
test('la cola separa indicación por confirmar, confirmada pendiente, lista y segunda atención',()=>{
 const rows=['PRE_PENDING','PENDING','READY','RESUMED','DECLINED'].map((status,id)=>({id,review:{status},procedures:[]}));const g=groupRows(rows);
 assert.deepEqual(g.pre_pending.map(r=>r.id),[0]);assert.deepEqual(g.pending.map(r=>r.id),[1]);assert.deepEqual(g.active.map(r=>r.id),[2]);assert.deepEqual(g.history.map(r=>r.id),[3,4]);
});
