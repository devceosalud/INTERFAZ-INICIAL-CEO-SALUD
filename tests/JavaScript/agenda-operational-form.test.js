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
