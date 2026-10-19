const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(
    path.resolve(__dirname, '../../resources/views/scheduling/agenda/partials/operational-registration.blade.php'),
    'utf8'
);
const workspace = fs.readFileSync(
    path.resolve(__dirname, '../../public/js/scheduling/agenda-operational-workspace.js'),
    'utf8'
);

test('OCR del adelanto reside dentro de Registrar adelanto, no entre documentos clínicos', () => {
    const paymentStart = source.indexOf('<details id="agenda-op-payment-panel">');
    const paymentEnd = source.indexOf('</details>', paymentStart);
    const documentsStart = source.indexOf('<details id="agenda-op-documents-panel">');
    const fileInput = source.indexOf('id="agenda-op-proof"');
    const receiptHost = source.indexOf('id="agenda-op-receipt-review"');
    assert.ok(paymentStart >= 0 && paymentEnd > paymentStart && documentsStart > paymentEnd);
    assert.ok(fileInput > paymentStart && fileInput < paymentEnd);
    assert.ok(receiptHost > paymentStart && receiptHost < paymentEnd);
    assert.equal((source.match(/id="agenda-op-proof"/g) || []).length, 1);
    assert.equal((source.match(/id="agenda-op-receipt-review"/g) || []).length, 1);
    assert.match(source.slice(documentsStart), /Documentos de la cita/);
});

test('OCR existente sigue entregando sugerencias a los campos de pago sin registrar automáticamente', () => {
    assert.match(workspace, /AppointmentReceiptReview/);
    assert.match(workspace, /paymentFields:\{amount:ui\.amount,method:ui\.method,operation:ui\.operation,origin:ui\.origin\}/);
    assert.match(workspace, /receipt\.append\(body,payload\)/);
});
