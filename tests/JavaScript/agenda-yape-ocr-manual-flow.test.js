const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const ocr=require('../../public/js/scheduling/appointment-receipt-ocr.js');
const workspace=fs.readFileSync(path.resolve(__dirname,'../../public/js/scheduling/agenda-operational-workspace.js'),'utf8');
const board=fs.readFileSync(path.resolve(__dirname,'../../public/js/scheduling/agenda.js'),'utf8');
const review=fs.readFileSync(path.resolve(__dirname,'../../public/js/scheduling/appointment-receipt-review.js'),'utf8');
const proofStorage=fs.readFileSync(path.resolve(__dirname,'../../app/Services/Scheduling/AppointmentProofStorage.php'),'utf8');

test('Yape amount is read when it is an integer S/50 (realistic receipt text)',()=>{
 for(const text of [
  'Yape\n¡Yapeaste!\nS/50\nNro. de operación: 22536760',
  'Yape\n¡Yapeaste!\nS/ 50\nNro. de operación: 22536760',
  'Yape\n¡Yapeaste!\n50\nNro. de operación: 22536760',
  'Yape\n¡Yapeaste!\nS/. 50\nNro. de operación: 22536760',
  'Yape\nMonto enviado S/ 50\nSaldo disponible S/500.00\nNro. de operación: 22536760',
 ]) {
  const result=ocr.parse(text);
  assert.equal(result.amount,'50.00',text);
  assert.equal(result.operation,'22536760',text);
  assert.equal(result.bank,'YAPE');
 }
});
test('operation is not inferred from unlabeled phone/DNI, amount ambiguity remains manual',()=>{
 const a=ocr.parse('Yape\nTeléfono 987654321\nDNI 12345678\nS/ 50');
 assert.equal(a.amount,'50.00');
 assert.equal(a.operation,null);
 const b=ocr.parse('Yape\nS/50\nS/60\nN.º de operación 0000123456');
 assert.equal(b.amount,null);
 assert.equal(b.ambiguous,true);
 assert.equal(b.operation,'0000123456');
});
test('Guardar reserva never sends payment/operation nor receipt and warns before discarding',()=>{
 assert.match(workspace,/payment: options\.pending \? model\.payment\('0','EFECTIVO','',''\) : payment\(\)/);
 assert.match(workspace,/if\(!options\.pending\)receipt\.append\(body,payload\)/);
 assert.match(workspace,/title:'Guardar reserva SIN registrar pago'/);
 assert.match(workspace,/showCancelButton:true/);
 assert.match(board,/operationalWorkspace\.readiness\(\), amount:'0'/);
});
test('Real payments still have duplicate guards; merely applying OCR does not send a request',()=>{
 assert.match(proofStorage,/payment_proof_hash/);
 assert.match(proofStorage,/function duplicate/);
 assert.doesNotMatch(review,/fetch\(|XMLHttpRequest|submitPayment/);
 assert.match(workspace,/const response = await request\(config\.endpoint,/);
 assert.match(workspace,/const response = await request\(base \+ '\/' \+ id \+ '\/payments',/);
});
