const test = require('node:test');
const assert = require('node:assert/strict');
const { moneyLines } = require('../../public/js/scheduling/agenda-withdrawal');
test('crédito/devolución envían solamente importes elegidos y referencia de voucher, sin copiar dinero', () => {
    assert.deepEqual(moneyLines([{ voucher_id: '3', amount: '20.00', available: '50.00' }, { voucher_id: '4', amount: '0', available: '40' }]), [{ voucher_id: 3, amount: '20.00' }]);
});
test('no se ofrecen importes fuera del saldo reservado/disponible ni texto en cantidades', () => {
    assert.throws(() => moneyLines([{ voucher_id: 1, amount: '51', available: '50' }]));
    assert.throws(() => moneyLines([{ voucher_id: 1, amount: '1.234', available: '50' }]));
});
