const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const ocr=require('../../public/js/scheduling/appointment-receipt-ocr.js');
const view=fs.readFileSync(path.resolve(__dirname,'../../resources/views/scheduling/agenda/partials/operational-registration.blade.php'),'utf8');
const modal=fs.readFileSync(path.resolve(__dirname,'../../resources/views/scheduling/agenda/partials/patient-modal.blade.php'),'utf8');
const scripts=fs.readFileSync(path.resolve(__dirname,'../../resources/views/scheduling/agenda/index.blade.php'),'utf8');
const js=fs.readFileSync(path.resolve(__dirname,'../../public/js/scheduling/agenda-operational-workspace.js'),'utf8');

test('Importe y operacion primero: separadores PEN y ceros a izquierda',()=>{
    const samples=[
        ['Yape\nMonto enviado: S/ 150.50\nN° de operación: 00012345678\nSaldo disponible S/ 12,345.00','150.50','00012345678'],
        ['PLIN\nImporte: S/ 1,250.00\nCodigo de operacion: 001238812','1250.00','001238812'],
        ['BCP\nTotal pagado: S/ 1.250,00\nNúmero de transacción: 00098765','1250.00','00098765'],
        ['BCP\nPEN 250,00\nNro. de operación 00654321','250.00','00654321'],
    ];
    for(const [receipt,amount,operation] of samples){
        const value=ocr.parse(receipt);
        assert.equal(value.amount,amount);
        assert.equal(value.operation,operation);
        assert.equal(value.needs_review,true);
    }
});
test('Números sin etiquetas no se inventan como operación; múltiples pagos quedan ambiguos',()=>{
    const a=ocr.parse('Yape\nCelular 987654321\nDNI 12345678\nS/ 40.00');
    assert.equal(a.operation,null);
    const b=ocr.parse('S/ 40.00\nS/ 50.00\nOperación 000123456\nOperación 000555666');
    assert.equal(b.amount,null);
    assert.equal(b.operation,null);
    assert.equal(b.ambiguous,true);
});
test('El pago presenta primero importe y método, y conserva TODOS los controles de negocio',()=>{
    const idx=id=>view.indexOf('id="agenda-op-'+id+'"');
    assert.ok(idx('price')>=0 && idx('paid')>=0 && idx('balance')>=0);
    assert.ok(idx('amount')<idx('method') && idx('method')<idx('operation'));
    assert.ok(idx('operation')<idx('proof') && idx('proof')<idx('receipt-review'));
    assert.ok(idx('advanced')>idx('receipt-review'));
    for(const key of ['indication','referred','referral-origin','rate','discount-type','discount-value','discount-reason','catalog-price','authorized','exemption-reason','waived','submit-payment','confirm-reservation','payment-error'])
        assert.ok(idx(key)>=0,key);
    assert.match(view,/id="agenda-op-payment-summary"/);
    assert.match(modal,/id="agenda-patient-error-summary"/);
    assert.match(scripts,/agenda-form-validation\.js/);
    assert.match(js,/error\.errors = response\.status === 422/);
});
