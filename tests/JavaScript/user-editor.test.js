const test = require('node:test');
const assert = require('node:assert/strict');
const api = require('../../public/js/admin/master/user/user');
test('edición envía sesión, CSRF y Accept JSON sin guardar credenciales', async () => {
    let sent;
    const result = await api.lookup('/api/admin/user/search', 12, 'csrf-test', async (url, options) => {
        sent = { url, options };
        return { ok: true, status: 200, headers: { get: () => 'application/json' }, json: async () => ({ user: { id: 12 } }) };
    });
    assert.equal(result.user.id, 12); assert.equal(sent.options.headers['X-CSRF-TOKEN'], 'csrf-test');
    assert.equal(sent.options.headers.Accept, 'application/json'); assert.equal(sent.options.credentials, 'same-origin');
});
test('HTML, sesión vencida y permisos no se intentan interpretar como JSON', async () => {
    for (const response of [{ status: 419 }, { status: 401 }, { status: 403 }, { status: 200, redirected: true }, { status: 500 }]) {
        let parsed = false;
        await assert.rejects(api.lookup('/api/admin/user/search', 1, 'csrf', async () => ({ ...response,
            headers: { get: () => 'text/html' }, json: async () => { parsed = true; throw new SyntaxError(); } })),
        error => !(error instanceof SyntaxError));
        assert.equal(parsed, false);
    }
});
