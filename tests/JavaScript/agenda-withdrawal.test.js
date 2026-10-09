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


test('operational withdrawal works without audit and sends a stable UUID instead of losing the click', async () => {
    const vm = require('node:vm'); const fs = require('node:fs');
    const elements = new Map(); const calls = []; const notices = [];
    const element = () => ({ hidden: false, value: '', checked: false, dataset: {}, handlers: {}, addEventListener(name, fn) { this.handlers[name] = fn; }, replaceChildren() {}, append() {}, querySelectorAll() { return []; } });
    const document = { getElementById(id) { if (!elements.has(id)) elements.set(id, element()); return elements.get(id); }, querySelector() { return { content: 'test-csrf' }; }, createElement: element };
    const window = { crypto: { randomUUID: () => '00000000-0000-4000-8000-000000000001' } };
    const context = { window, document, Intl, Date, fetch: async (url, options) => { calls.push({ url, options }); return { ok: true, json: async () => url.endsWith('/history') ? { estado_cita: 'PROGRAMADO', can_withdraw: true, can_cancel: true, can_no_show: true, events: [], available_credit: [], refund_requests: [] } : {} }; } };
    vm.runInNewContext(fs.readFileSync(require.resolve('../../public/js/scheduling/agenda-withdrawal'), 'utf8'), context);
    const workflow = window.AgendaWithdrawal.start({ base: '/appointments', canWorkflow: true, canAudit: false, refresh: async () => { document.getElementById('agenda-withdrawal-date').value = ''; document.getElementById('agenda-withdrawal-time').value = ''; }, notice: text => notices.push(text), error: () => 'error' });
    await workflow.select(7); document.getElementById('agenda-withdrawal-reason').value = 'Retiro ficticio';
    document.getElementById('agenda-withdrawal-present').checked = true; document.getElementById('agenda-withdrawal-action').value = 'PENDIENTE';
    await document.getElementById('agenda-withdrawal-submit').handlers.click();
    const mutation = calls.find(call => call.options.method === 'POST');
    assert.ok(mutation); assert.equal(mutation.url, '/appointments/7/withdraw');
    assert.equal(JSON.parse(mutation.options.body).request_key, '00000000-0000-4000-8000-000000000001');
    assert.equal(JSON.parse(mutation.options.body).was_present, true);
    assert.match(document.getElementById('agenda-withdrawal-status').textContent, /Retiro registrado/);
    assert.equal(notices.length, 0);

    document.getElementById('agenda-withdrawal-date').value = '2026-12-03';
    document.getElementById('agenda-withdrawal-time').value = '10:20';
    await document.getElementById('agenda-withdrawal-rebook').handlers.click();
    assert.match(document.getElementById('agenda-withdrawal-status').textContent, /03\/12\/2026 a las 10:20/);
    for (const action of ['cancel', 'no-show']) {
        document.getElementById('agenda-ending-kind').value = action;
        document.getElementById('agenda-ending-kind').handlers.change();
        assert.equal(document.getElementById('agenda-withdrawal-presence-field').hidden, true);
        await document.getElementById('agenda-withdrawal-submit').handlers.click();
        const call = calls.filter(c => c.options.method === 'POST').at(-1);
        assert.equal(call.url, '/appointments/7/' + action);
        assert.equal('was_present' in JSON.parse(call.options.body), false);
    }
});
